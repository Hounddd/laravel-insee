# Laravel Insee

This package is a Laravel wrapper allowing you to lookup SIREN and SIRET numbers of French businesses and nonprofit associations, attributed by [Insee](https://insee.fr/en/accueil) (Institut national de la statistique et des études économiques).

Laravel Insee uses the [Sirene](https://api.insee.fr/catalogue/) API in his latest version.  
The configuration file and environment variables allow you to specify an API version to be used.

This package relies on LAravel's cache to hold the token until it expires.

## Installation

You can install this package via Composer:

``` bash
composer require nspehler/laravel-insee
```
## Requirements

This package requires Laravel 9 with PHP 8 or higher.


## Configuration

To get started, sign up on [https://api.insee.fr](https://api.insee.fr) and create a new application. From the "Production Keys" tab, you'll be able to generate your Consumer Key and Consumer Secret.

Make sure to subscribe your new app to the Sirene V3 API to grant it access.

Then, you can add your production keys as environment variables in your `.env` file:
```
INSEE_CONSUMER_KEY=
INSEE_CONSUMER_SECRET=
INSEE_SIRENE_API_VERSION=
```
By default, the API will be called on the latest version, but you can specify which version to use with the `INSEE_SIRENE_API_VERSION` variable.

Optionally, you can edit the name of these variables by publishing the configuration file:
```
php artisan vendor:publish --provider="NSpehler\LaravelInsee\InseeServiceProvider" --tag="config"
```

## Usage

Use the `Insee` facade to lookup a SIREN or SIRET number:

``` php
Insee::siren('840 745 111');
Insee::siret('840 745 111 00012');
```

## Credits

- [Nicolas Spehler](https://nspehler.com)
- [Moon](https://moon.xyz)
- [Supplement-Bacon](https://github.com/Supplement-Bacon)

## License
The MIT License (MIT). Please see [License File](LICENSE) for more information.
