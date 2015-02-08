# Phergie-xREL-Plugin

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) plugin to access the [xREL.to](http://xrel.to) API.

## Install

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `~`.

```
composer require hashworks/phergie-xrel-plugin
```

See Phergie documentation for more information on
[installing and enabling plugins](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#plugins).

## Configuration

```php
// dependency
new \WyriHaximus\Phergie\Plugin\Dns\Plugin,
new \WyriHaximus\Phergie\Plugin\Http\Plugin(array('dnsResolverEvent' => 'dns.resolver')),
new \hashworks\Phergie\Plugin\xREL\Plugin(array(
    // Optional. Searches for release dirnames in all messages and applies the nfo command on them.
    'parseAllMessages' => false,
    // Optional. Limit of posts per command.
    'limit' => 5
)),
```

## Syntax
`!upcoming` Responds with a list of upcoming movies.<br/>
`!latest [[hd-]movie|[hd-]tv|game|update|console|[hd-]xxx]` Responds with a list of latest releases.<br/>
`!hot [movie|tv|game|console]` Responds with a list of latest hot releases.<br/>
`!nfo <dirname>` Responds with a nfo link for a scene dirname.