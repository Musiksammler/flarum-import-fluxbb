<?php

namespace Packrats\ImportFluxBB\Importer;

use DateTime;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use PDO;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class Topics
{
    /**
     * @var ConnectionInterface
     */
    private $database;
    /**
     * @var PDO
     */
    private $fluxBBDatabase;
    /**
     * @var string
     */
    private $fluxBBPrefix;

    public function __construct(ConnectionInterface $database)
    {
        $this->database = $database;
    }

    public function execute(OutputInterface $output, PDO $fluxBBDatabase, string $fluxBBPrefix)
    {
        $this->fluxBBDatabase = $fluxBBDatabase;
        $this->fluxBBPrefix = $fluxBBPrefix;
        $output->writeln('Importing topics...');

        $forums = $this->database
            ->table('tags')
            ->select(['id', 'name'])
            ->where('parent_id', '!=', null)
            ->orderBy('id')
            ->get()
            ->all();
        $forumsIds = [];
        foreach ($forums as $forum) {
            $forumsIds[$forum->name] = $forum->id;
        }

        $fields = [
            't.id',
            't.poster',
            't.subject',
            't.posted',
            't.first_post_id',
            't.last_post',
            't.last_post_id',
            't.last_poster',
            't.num_views',
            't.num_replies',
            't.closed',
            't.sticky',
            't.moved_to',
            't.forum_id',
            'f.forum_name'
        ];
        $sql = sprintf(
            "SELECT %s FROM %s AS t JOIN %s AS f ON t.`forum_id` = f.`id` WHERE `moved_to` IS NULL ORDER BY `id`",
            implode(', ', $fields),
            $this->fluxBBPrefix .'topics',
            $this->fluxBBPrefix .'forums',
        );
        $stmt = $this->fluxBBDatabase->query($sql);
        $topics = $stmt->fetchAll(PDO::FETCH_OBJ);

        $progressBar = new ProgressBar($output, count($topics));

        $this->database->statement('SET FOREIGN_KEY_CHECKS=0');
        $solvedTagId = $this->createSolvedTag();

        foreach ($topics as $topic) {
            $numberOfPosts = $topic->num_replies + 1;

            $forumName = $topic->forum_name;
            if ($topic->forum_name === 'Offtopic') {
                $forumName = 'Kaffeeklatsch';
            }
            $tagIds = [$this->getParentTagId($forumName), $forumsIds[$forumName]];

            if ($this->replaceSolvedHintByTag($topic->subject)) {
                $tagIds[] = $solvedTagId;
            }

            $this->database
                ->table('discussions')
                ->insert(
                    [
                        'id' => $topic->id,
                        'title' => $topic->subject,
                        'comment_count' => $numberOfPosts,
                        'participant_count' => $this->getParticipantCountByTopic($topic->id),
                        'post_number_index' => $numberOfPosts,
                        'created_at' => (new DateTime())->setTimestamp($topic->posted),
                        'user_id' => $this->getUserByPost($topic->first_post_id),
                        'first_post_id' => $topic->first_post_id,
                        'last_posted_at' => (new DateTime())->setTimestamp($topic->last_post),
                        'last_posted_user_id' => $this->getUserByPost($topic->last_post_id),
                        'last_post_id' => $topic->last_post_id,
                        'last_post_number' => $numberOfPosts,
                        'hidden_at' => null,
                        'hidden_user_id' => null,
                        'slug' => Str::slug(preg_replace('/\.+/', '-', $topic->subject), '-', 'de'),
                        'is_private' => 0,
                        'is_approved' => 1,
                        'is_locked' => $topic->closed,
                        'is_sticky' => $topic->sticky
                    ]
                );

            foreach ($tagIds as $tagId) {
                $this->database
                    ->table('discussion_tag')
                    ->insert(
                        [
                            'discussion_id' => $topic->id,
                            'tag_id' => $tagId,
                        ]
                    );
            }

            $progressBar->advance();
        }
        $this->database->statement('SET FOREIGN_KEY_CHECKS=1');
        $progressBar->finish();

        $output->writeln('');
    }

    private function getUserByPost(int $postId): ?int
    {
        $sql = sprintf(
            "SELECT `poster`, `poster_id` FROM %s WHERE `id` = :postId",
            $this->fluxBBPrefix .'posts'
        );
        $stmt = $this->fluxBBDatabase->prepare($sql);
        $stmt->bindValue('postId', $postId, PDO::PARAM_INT);
        $stmt->execute();

        $post = $stmt->fetch(PDO::FETCH_OBJ);

        if ($post->poster_id > 1) {
            return $post->poster_id;
        } else {
            return $this->getUserByName($post->poster);
        }
    }

    private function getUserByName(string $nickname): ?int
    {
        $sql = sprintf(
            "SELECT `id` FROM %s WHERE `username` = :nickname",
            $this->fluxBBPrefix .'users'
        );
        $stmt = $this->fluxBBDatabase->prepare($sql);
        $stmt->bindValue('nickname', $nickname);
        $stmt->execute();
        $userId = $stmt->fetchColumn();

        return $userId ? (int)$userId : null;
    }

    private function getParticipantCountByTopic(int $topicId): int
    {
        $sql = sprintf(
            "SELECT COUNT(`poster`) FROM %s WHERE `topic_id` = :topicId GROUP BY `poster`",
            $this->fluxBBPrefix .'posts'
        );
        $stmt = $this->fluxBBDatabase->prepare($sql);
        $stmt->bindValue('topicId', $topicId, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    private function getParentTagId(string $tagName): int
    {
        $tag = $this->database
            ->table('tags')
            ->select('parent_id')
            ->where('name', '=', $tagName)
            ->get()
            ->first();

        return $tag->parent_id;
    }

    private function createSolvedTag(): int
    {
        return $this->database
            ->table('tags')
            ->insertGetId(
                [
                    'name' => 'gelöst',
                    'slug' => 'geloest',
                    'description' => 'Fragen die beantwortet und Themen die gelöst wurden',
                    'color' => '#2e8b57',
                    'is_hidden' => 1,
                    'icon' => 'fas fa-check-square',
                ]
            );
    }

    private function replaceSolvedHintByTag(string &$title): bool
    {
        $solvedHint = '(gel(ö|oe)(s|ss|ß)t|(re)?solved|erledigt|done|geschlossen)';
        $count = 0;
        $title = preg_replace(
            [
                '/^\s*(\[|\()\s*' . $solvedHint . '\s*(\]|\))\s*/i',
                '/\s*(\[|\()\s*' . $solvedHint . '\s*(\]|\))\s*$/i',
                '/^\s*' . $solvedHint . ':\s*/i'
            ],
            '',
            $title,
            -1,
            $count
        );
        return $count > 0;
    }
}
