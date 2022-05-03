<?php

namespace Packrats\ImportFluxBB\Importer;

use Illuminate\Database\ConnectionInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class Bans
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
        $output->writeln('Importing bans...');

        $bans = $this->database
            ->table($this->fluxBBDatabase . '.' .$this->fluxBBPrefix .'bans')
            ->select(
                [
                    'id',
                    'username',
                    'ip',
                    'email',
                    'message',
                    'expire',
                    'ban_creator'
                ]
            )
            ->where('username', '!=', null, 'or')
            ->where('email', '!=', null, 'or')
            ->orderBy('id')
            ->get()
            ->all();

        $progressBar = new ProgressBar($output, count($bans));

        foreach ($bans as $ban) {
            $table = $this->database
                ->table('users');
            if ($ban->username) {
                $table = $table->where('nickname', '=', $ban->username, 'or');
            }
            if ($ban->email) {
                $table = $table->where('email', '=', $ban->email, 'or');
            }
            $table->update(
                [
                    'suspended_until' => (new \DateTime())->setTimestamp($ban->expire ?? strtotime('+1 years'))
                ]
            );
            $progressBar->advance();
        }
        $progressBar->finish();

        $output->writeln('');
    }
}
