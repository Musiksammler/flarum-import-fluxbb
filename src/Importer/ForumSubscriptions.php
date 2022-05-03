<?php

namespace Packrats\ImportFluxBB\Importer;

use Illuminate\Database\ConnectionInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class ForumSubscriptions
{
    /**
     * @var ConnectionInterface
     */
    private $database;
    /**
     * @var string
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

    public function execute(OutputInterface $output, string $fluxBBDatabase, string $fluxBBPrefix)
    {
        $this->fluxBBDatabase = $fluxBBDatabase;
        $this->fluxBBPrefix = $fluxBBPrefix;
        $output->writeln('Importing forum_subscriptions...');

        $topicSubscriptions = $this->database
            ->table($this->fluxBBDatabase . '.' .$this->fluxBBPrefix .'forum_subscriptions')
            ->select(
                [
                    'user_id',
                    'forum_id'
                ]
            )
            ->orderBy('forum_id')
            ->get()
            ->all();

        $progressBar = new ProgressBar($output, count($topicSubscriptions));

        foreach ($topicSubscriptions as $topicSubscription) {
            $this->database
                ->table('tag_user')
                ->insert(
                    [
                        'user_id' => $topicSubscription->user_id,
                        'tag_id' => $topicSubscription->forum_id,
                        'marked_as_read_at' => null,
                        'is_hidden' => 0
                    ]
                );
            $progressBar->advance();
        }
        $progressBar->finish();

        $output->writeln('');
    }
}
