<?php

namespace Packrats\ImportFluxBB\Importer;

use Flarum\Foundation\Paths;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class Avatars
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
    /**
     * @var string
     */
    private $avatarsDir;
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ConnectionInterface $database, ContainerInterface $container)
    {
        $this->database = $database;
        $this->container = $container;
    }

    public function execute(OutputInterface $output, string $fluxBBDatabase, string $fluxBBPrefix, string $avatarsDir)
    {
        $this->fluxBBDatabase = $fluxBBDatabase;
        $this->fluxBBPrefix = $fluxBBPrefix;
        $this->avatarsDir = $avatarsDir;
        $output->writeln('Importing avatars...');

        $users = $this->database
            ->table($this->fluxBBDatabase . '.' .$this->fluxBBPrefix .'users')
            ->select(['id'])
            ->where('username', '!=', 'Guest')
            ->orderBy('id')
            ->get()
            ->all();

        $progressBar = new ProgressBar($output, count($users));

        foreach ($users as $user) {
            $this->database
                ->table('users')
                ->where('id', '=', $user->id)
                ->update(['avatar_url' => $this->createAvatarUrl($user->id)]);
            $progressBar->advance();
        }
        $progressBar->finish();

        $output->writeln('');
    }

    /**
     * @param int $userId
     * @return string|null
     */
    private function createAvatarUrl(int $userId): ?string
    {
        $avatarFile = glob($this->avatarsDir . '/' . $userId . '.*');
        if (!$avatarFile) {
            return null;
        }
        $avatarFile = $avatarFile[0];

        $newFileName = Str::random() . '.png';
        $newDir = $this->container[Paths::class]->public . '/assets/avatars';
		if (!is_dir($newDir)) {
			mkdir($newDir);
		}
        $newPath = $newDir . '/' . $newFileName;
        if (file_exists($newPath)) {
            throw new \RuntimeException('Avatar already exists: ' . $newFileName);
        }

        Image::configure(['driver' => 'imagick']);
        $image = Image::make($avatarFile);
        if (!Str::endsWith($avatarFile, '.png')
            || $image->getWidth() !== $image->getHeight()
            || $image->getWidth() > 100) {
            $newSize = max($image->getWidth(), $image->getHeight());
            if ($newSize > 100) {
                $newSize = 100;
            }
            $encodedImage = $image->orientate()->fit($newSize, $newSize)->encode('png');
            file_put_contents($newPath, $encodedImage);
        } else {
            copy($avatarFile, $newPath);
        }
        system('optipng -o 5 -strip all -snip -quiet ' . $newPath);

        return $newFileName;
    }
}
