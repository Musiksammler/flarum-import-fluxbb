<?php

namespace Packrats\ImportFluxBB\Importer;

use Illuminate\Database\ConnectionInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class TopicSubscriptions
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
        $output->writeln('Importing topic_subscriptions...');

        $topicSubscriptions = $this->database
            ->table($this->fluxBBDatabase . '.' .$this->fluxBBPrefix .'topic_subscriptions')
            ->select(
                [
                    'user_id',
                    'topic_id'
                ]
            )
            ->orderBy('topic_id')
            ->get()
            ->all();

        $progressBar = new ProgressBar($output, count($topicSubscriptions));

        foreach ($topicSubscriptions as $topicSubscription) {
            $this->database
                ->table('discussion_user')
                ->insert(
                    [
                        'user_id' => $topicSubscription->user_id,
                        'discussion_id' => $topicSubscription->topic_id,
                        'last_read_at' => null,
                        'last_read_post_number' => null,
                        'subscription' => 'follow'
                    ]
                );
            $progressBar->advance();
        }
        $progressBar->finish();

        $output->writeln('');
    }
}
