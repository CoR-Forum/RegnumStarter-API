## Installation

### Requirements

- php
- composer (e.g. ``bew install composer``)

### Process

1. Copy ``config/config.php.example`` to ``config/config.php`` and change the variables.
2. Run the setup script, e.g. ``php setup.php``
3. Optional: Setup a ``.htaccess`` file for routing:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/$1 [L]
```

4. Install required modules:

```
composer install
```

5. Run PHP

```
php -S 127.0.0.1:5500 -t .
```

### Optional

Optional for FiveServer (macOS example)

``fiveserver.config.js``

```
module.exports = {
    php: "/opt/homebrew/bin/php"
    }
```