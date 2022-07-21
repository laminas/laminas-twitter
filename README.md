# laminas-twitter

[![Build Status](https://github.com/laminas/laminas-twitter/workflows/Continuous%20Integration/badge.svg)](https://github.com/laminas/laminas-twitter/actions?query=workflow%3A"Continuous+Integration")

> ## 🇷🇺 Русским гражданам
>
> Мы, участники Laminas, родились и живем в разных странах. У многих из нас есть друзья, родственники и коллеги как в России, так и в Украине. Некоторые из нас родились в России. Некоторые из нас живут в России. У некоторых бабушки и дедушки сражались с фашистами во Второй мировой войне. Здесь никто не поддерживает фашизм.
>
> У одного из нас есть украинская родственница, которая спаслась из дома вместе с сыном. Поезд задержался из-за бомбежки на дороге впереди. У нас есть друзья, которые прячутся в бомбоубежищах. Мы с тревогой ждем весточки от них после воздушных налетов, которые беспорядочно наносят удары и попадают по больницам, школам, детским садам и домам. Мы не берем это из каких-либо СМИ. Мы наблюдаем это напрямую.
>
> Вы доверяете нам достаточно, чтоб использовать наши программы, и мы просим вас довериться нам вновь. Мы нуждаемся в помощи. Выходите и протестуйте против этой бесполезной войны. Остановите кровопролитие. Скажите "Нет войне!"
>
> ## 🇺🇸 To Citizens of Russia
>
> We at Laminas come from all over the world. Many of us have friends, family and colleagues in both Russia and Ukraine. Some of us were born in Russia. Some of us currently live in Russia. Some have grandparents who fought Nazis in World War II. Nobody here supports fascism.
>
> One team member has a Ukrainian relative who fled her home with her son. The train was delayed due to bombing on the road ahead. We have friends who are hiding in bomb shelters. We anxiously follow up on them after the air raids, which indiscriminately fire at hospitals, schools, kindergartens and houses. We're not taking this from any media. These are our actual experiences.
>
> You trust us enough to use our software. We ask that you trust us to say the truth on this. We need your help. Go out and protest this unnecessary war. Stop the bloodshed. Say "stop the war!"

Provides an object oriented PHP wrapper for the [Twitter API](https://developer.twitter.com/en/docs).

## Installation

Run the following to install this library:

```bash
$ composer require laminas/laminas-twitter
```

## Usage

Instantiate the `Twitter` class by providing your Twitter consumer key and
secret, as well as the access token and secret:

```php
use Laminas\Twitter\Twitter;

$twitter = new Twitter([
    'access_token' => [
        'token' => '<token value>',
        'secret' => '<token secret value>',
    ],
    'oauth_options' => [
        'consumerKey' => '<consumer key value>',
        'consumerSecret' => '<consumer secret value>',
    ],
]);
```

Once you have done that, you may start making calls to the API. This can be done
in one of three ways:

- Using direct method calls on the `Twitter` class. A full list is provided
  below.
- Using the "proxy" functionality. In these cases, you will provide the first
  path element of the API, and then call a method on it:
  `$twitter->statuses->update($message)`.
- Using the `get()` or `post()` methods.

## Available methods

- `accountVerifyCredentials() : Response`
- `applicationRateLimitStatus() : Response`
- `blocksCreate($id) : Response`
- `blocksDestroy($id) : Response`
- `blocksIds(int $cursor = -1) : Response`
- `blocksList(int $cursor = -1) : Response`
- `directMessagesDestroy($id) : Response`
- `directMessagesMessages(array $options = []) : Response`
- `directMessagesNew($user, string $text, array $extraParams = []) : Response`
- `directMessagesEventsNew($user, string $text, array $extraParams = []) : Response`
- `directMessagesSent(array $options = []) : Response`
- `favoritesCreate($id) : Response`
- `favoritesDestroy($id) : Response`
- `favoritesList(array $options = []) : Response`
- `followersIds($id, array $params = []) : Response`
- `friendsIds($id, array $params = []) : Response`
- `friendshipsCreate($id, array $params = []) : Response`
- `friendshipsLookup($id, array $params = []) : Response`
- `friendshipsDestroy($id) : Response`
- `listsMembers($listIdOrSlug, array $params = []) : Response`
- `listsMemberships($id, array $params = []) : Response`
- `listsSubscribers($id, array $params = []) : Response`
- `searchTweets(string $query, array $options = []) : Response`
- `statusesDestroy($id) : Response`
- `statusesHomeTimeline(array $options = []) : Response`
- `statusesMentionsTimeline(array $options = []) : Response`
- `statusesSample() : Response`
- `statusesShow($id, array $options = []) : Response`
- `statusesUpdate(string $status, $inReplyToStatusId = null, $extraAttributes = []) : Response`
- `statusesUserTimeline(array $options = []) : Response`
- `usersLookup($id, array $params = []) : Response`
- `usersSearch(string $query, array $options = []) : Response`
- `usersShow($id) : Response`

## Proxy Properties

The following proxy properties are allowed:

- account
- application
- blocks
- directmessages
- favorites
- followers
- friends
- friendships
- lists
- search
- statuses
- users

In each case, you can identify available methods for the proxy by comparing the
proxy name to the above list of methods. As an example, the `users` proxy allows
the following:

```php
$twitter->users->lookup($id, array $params = []);
$twitter->users->search(string $query, array $options = []);
$twitter->users->show($id);
```

## Direct access

The Twitter API has dozens of endpoints, some more popular and/or useful than
others. As such, we are only providing a subset of what is available.

However, we allow you to access any endpoint via either the `get()` or `post()`
methods, which have the following signatures:

```php
public function get(string $path, array $query = []) : Response;
public function post(string $path, $data = null) : Response;
```

In each case, the `$path` is the API endpoint as detailed in the Twitter API
documentation, minus any `.json` suffix, and the method name corresponds to
whether the request happens via HTTP GET or POST.

For HTTP GET requests, the `$query` argument provides any query string
parameters you want to pass for that endpoint. As an example, if you were
requesting `statuses/home_timeline`, you might pass `count` or `since_id`.

For HTTP POST requests, the `$data` argument can be one of:

- An associative array of data.
- A serializable object of data.
- A string representing the raw payload.

The data to provide will vary based on the endpoint.

## Media uploads

Since version 3.0, we have supported media uploads via the classes
`Laminas\Twitter\Media`, `Image`, and `Video`. In each case, you will
instantiate the appropriate class with the local filesystem path of the image to
upload and the media type, followed by calling `upload()` with a properly
configured HTTP client. The response will contain a `media_id` property, which
you can then provide via the `media_ids` parameter when posting a status:

```php
$image = new Image('data/logo.png', 'image/png');
$response = $image->upload($twitter->getHttpClient());

$twitter->statusUpdate(
    'A post with an image',
    null,
    ['media_ids' => [$response->media_id]]
);
```

When providing media for direct messages, you must provide additional flags to
the media class's constructor:

- A flag indicating it is for a direct message
- A flag indicating whether or not the uploaded media may be shared/re-used in
  other direct messages.

```php
$image = new Image(
    'data/logo.png',
    'image/png',
    $forDirectMessage = true,
    $shared = false
);
$upload = $image->upload($twitter->getHttpClient());
```

Unlike non-DM media uploads, the identifier will be in the `id_str` parameter of
the returned upload instance; use that as a `media_id` in your DM:

```php
$twitter->directmessagesEventsNew(
    $user,
    $message,
    ['media_id' => $upload->id_str]
);
```

Note: direct messages only support a single attachment.

## Rate limiting

As of version 3.0, we now provide introspection of Twitter's rate limit headers,
allowing you to act on them:

```php
$response = $twitter->statusUpdate('A post');
$rateLimit = $response->getRateLimit();
if ($rateLimit->remaining === 0) {
    // Time to back off!
    sleep($rateLimit->reset); // seconds left until reset
}
```
