## FluxBB Importer

This [Flarum](https://flarum.org/) extension is specific to [forum.musik-sammler.de](https://forum.musik-sammler.de/). You might
find its code useful to implement your own solution.

### Installation

```sh
composer require packrats/flarum-import-fluxbb
```

### Usage

```sh
./flarum app:import-from-fluxbb  [<fluxbb-database> [<fluxbb-prefix> [[<avatars-dir>]]]
```

P.S.: And yes, i hate laravel...
