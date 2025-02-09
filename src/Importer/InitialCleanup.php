<?php

namespace Packrats\ImportFluxBB\Importer;

use Flarum\Foundation\Paths;
use Illuminate\Database\ConnectionInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitialCleanup
{
    /**
     * @var ConnectionInterface
     */
    private $database;
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ConnectionInterface $database, ContainerInterface $container)
    {
        $this->database = $database;
        $this->container = $container;
    }

    public function execute(OutputInterface $output)
    {
        $output->writeln('Initial cleanup...');

        $this->database->statement('SET FOREIGN_KEY_CHECKS=0');

        $this->database->statement('ALTER TABLE '.$this->database->getTablePrefix().'users DROP INDEX `users_email_unique`');
        $this->database->statement('ALTER TABLE '.$this->database->getTablePrefix().'users ADD INDEX `users_email_unique` (`email`)');

        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'groups');
        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'group_user');
        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'group_permission');
        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'discussions');
        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'discussion_tag');
        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'discussion_user');
        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'posts');
        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'tags');
        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'tag_user');
//        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'users');
        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'flags');
        $this->database->statement('TRUNCATE TABLE '.$this->database->getTablePrefix().'post_mentions_user');

        $this->database->statement('SET FOREIGN_KEY_CHECKS=1');

//        foreach (glob($this->container[Paths::class]->public . '/assets/avatars/*.*') as $avatar) {
//            unlink($avatar);
//        }
    }
}
