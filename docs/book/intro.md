# laminas-twitter

## Introduction

`Laminas\Twitter\Twitter` provides a client for the
[Twitter API](https://developer.twitter.com/en/docs/api-reference-index).
`Laminas\Twitter\Twitter` allows you to query the public timeline.
If you provide a username and OAuth details for Twitter, or your access
token and secret, it will allow you to get and update your status, reply
to friends, direct message friends, mark tweets as favorites, and much
more.

`Laminas\Twitter\Twitter` wraps all web service operations,
including OAuth, and all methods return an instance of
`Laminas\Twitter\Response`.

`Laminas\Twitter\Twitter` is broken up into subsections so you can
easily identify which type of call is being requested.

- `account` allows you to check that your account credentials are valid
- `application` allows you to check your API rate limits.
- `blocks` blocks and unblocks users from following you.
- `directMessages` retrieves the authenticated user's received direct
  messages, deletes direct messages, and sends new direct messages.
- `favorites` lists, creates, and removes favorite tweets.
- `friendships` creates and removes friendships for the authenticated
  user.
- `search` allows you to search statuses for specific criteria.
- `statuses` retrieves the public and user timelines and shows,
  updates, destroys, and retrieves replies for the authenticated user.
- `users` retrieves friends and followers for the authenticated user
  and returns extended information about a passed user.

## Installation

Use [Composer](https://getcomposer.org) to install this package:

```bash
composer require laminas/laminas-twitter
```

## Quick Start

To get started, first you'll need to either create a new application
with Twitter, or get the details of an existing one you control. To do
this:

- Go to <https://developer.twitter.com/> and sign in.
- Go to <https://apps.twitter.com/>
- Either create a new application, or select an existing one.
- On the application's settings page, grab the following information:
  - From the header "OAuth settings", grab the "Consumer key" and
    "Consumer secret" values.
  - From the header "Your access token", grab the "Access token" and
    "Access token secret" values.

Armed with this information, you can now configure and create your
`Laminas\Twitter\Twitter` instance:

```php
$config = [
    'access_token' => [
        'token'  => 'twitter-access-token-here',
        'secret' => 'twitter-access-secret-here',
    ],
    'oauth_options' => [
        'consumerKey' => 'twitter-consumer-key-here',
        'consumerSecret' => 'twitter-consumer-secret-here',
    ],
    'http_client_options' => [
        'adapter' => Laminas\Http\Client\Adapter\Curl::class,
        'curloptions' => [
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ],
    ],
];

$twitter = new Laminas\Twitter\Twitter($config);
```

Make sure you substitute the values you discovered earlier in the
configuration before attempting to connect.

> ### Note on SSL certificate verification
>
> Twitter has a known issue with the SSL certificate for their API
> endpoints, which requires that you use insecure settings for the SSL
> certificate verification.

Once you have the client configured, you can start consuming it:

```php
// Verify your credentials:
$response = $twitter->account->verifyCredentials();
if (!$response->isSuccess()) {
    die('Something is wrong with my credentials!');
}

// Search for something:
$response = $twitter->search->tweets('#zf2');
foreach ($response->toValue() as $tweet) {
    printf("%s\n- (%s)\n", $tweet->text, $tweet->user->name);
}

// Tweet something:
$twitter->statuses->update('Hello world!');
```

Every action you take returns a `Laminas\Twitter\Response`
object. This object contains some general purpose methods for
determining the status of the response (`isSuccess()`, `isError()`), and
otherwise acts as a value object containing the data returned.
Essentially, if the response returns an object, you will be able to
access the members listed by the
[Twitter API documentation](https://developer.twitter.com/en/docs/api-reference-index).
In the case of responses that return arrays, such as the `$twitter->search->tweets()`
example shown earlier, you should use the `toValue()` method of the
response to retrieve the array.

If you wish to dive in more into how authentication works, and what
methods are exposed, keep reading!

## Authentication

With the exception of fetching the public timeline,
`Laminas\Twitter\Twitter` requires authentication as a valid user.
This is achieved using the OAuth authentication protocol. OAuth is the
only supported authentication mode for Twitter as of August 2010. The
OAuth implementation used by `Laminas\Twitter\Twitter` is
`laminas-oauth`.

### Creating the Twitter Class

`Laminas\Twitter\Twitter` must authorize itself, on behalf of a
user, before use with the Twitter API (except for public timeline). This
must be accomplished using OAuth since Twitter has disabled it's basic
HTTP authentication as of August 2010.

There are two options to establishing authorization. The first is to
implement the workflow of `laminas-oauth` via `Laminas\Twitter\Twitter`
which proxies to an internal `Laminas\OAuth\Consumer` object. Please
refer to the `laminas-oauth` documentation for a full example of this
workflow - you can call all documented `Laminas\OAuth\Consumer` methods
on `Laminas\Twitter\Twitter` including constructor options. You may
also use `laminas-oauth` directly and only pass the resulting access
token into `Laminas\Twitter\Twitter`. This is the normal workflow once
you have established a reusable access token for a particular Twitter
user. The resulting OAuth access token should be stored to a database
for future use (otherwise you will need to authorize for every new
instance of `Laminas\Twitter\Twitter`). Bear in mind that authorization
via OAuth results in your user being redirected to Twitter to give their
consent to the requested authorization (this is not repeated for stored
access tokens). This will require additional work (i.e. redirecting
users and hosting a callback URL) over the previous HTTP authentication
mechanism where a user just needed to allow applications to store their
username and password.

The following example demonstrates setting up
`Laminas\Twitter\Twitter` which is given an already established
OAuth access token. Please refer to the `laminas-oauth` documentation
to understand the workflow involved. The access token is a serializable
object, so you may store the serialized object to a database, and
unserialize it at retrieval time before passing the objects into
`Laminas\Twitter\Twitter`. The `laminas-oauth` documentation
demonstrates the workflow and objects involved.

```php
/**
 * We assume $serializedToken is the serialized token retrieved from a database
 * or even $_SESSION (if following the simple laminas-oauth documented example)
 */
$token = unserialize($serializedToken);

$twitter = new Laminas\Twitter\Twitter([
    'accessToken' => $token,
    'oauth_options' => [
        'username' => 'johndoe',
    ],
]);

// verify user's credentials with Twitter
$response = $twitter->account->verifyCredentials();
```

> ### Note on Twitter OAuth Authentication
>
> In order to authenticate with Twitter, ALL applications MUST be
> registered with Twitter in order to receive a Consumer Key and
> Consumer Secret to be used when authenticating with OAuth. This can
> not be reused across multiple applications - you must register each
> new application separately. Twitter access tokens have no expiry date,
> so storing them to a database is advised (they can, of course, be
> refreshed simply be repeating the OAuth authorization process). This
> can only be done while interacting with the user associated with that
> access token.
>
> The previous pre-OAuth version of `Laminas\Twitter\Twitter`
> allowed passing in a username as the first parameter rather than
> within an array. This is no longer supported.

If you have registered an application with Twitter, you can also use the
access token and access token secret they provide you in order to setup
the OAuth consumer. This can be done as follows:

```php
$twitter = new Laminas\Twitter\Twitter([
    'access_token' => [ // or use "accessToken" as the key; both work
        'token' => 'your-access-token',
        'secret' => 'your-access-token-secret',
    ],
    'oauth_options' => [ // or use "oauthOptions" as the key; both work
        'consumerKey' => 'your-consumer-key',
        'consumerSecret' => 'your-consumer-secret',
    ],
]);
```

If desired, you can also specify a specific HTTP client instance to use,
or provide configuration for the HTTP client. To provide the HTTP
client, use the `http_client` or `httpClient` key, and provide an
instance. To provide HTTP client configuration for setting up an
instance, use the key `http_client_options` or `httpClientOptions`. As a
full example:

```php
$twitter = new Laminas\Twitter\Twitter([
    'access_token' => [ // or use "accessToken" as the key; both work
        'token' => 'your-access-token',
        'secret' => 'your-access-token-secret',
    ],
    'oauth_options' => [ // or use "oauthOptions" as the key; both work
        'consumerKey' => 'your-consumer-key',
        'consumerSecret' => 'your-consumer-secret',
    ],
    'http_client_options' => [
        'adapter' => Laminas\Http\Client\Adapter\Curl::class,
    ],
]);
```

## Account Methods

### Verifying credentials

`verifyCredentials()` tests if supplied user credentials are valid with
minimal overhead.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->account->verifyCredentials();
```

## Application Methods

### Rating limit status

`rateLimitStatus()` returns the remaining number of API requests
available to the authenticating user before the API limit is reached
for the current hour.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->application->rateLimitStatus();
$userTimelineLimit = $response->resources->statuses->{'/statuses/user_timeline'}->remaining;
```

## Blocking Methods

### Blocking a user

`create()` blocks the user specified in the `id` parameter as the
authenticating user and destroys a friendship to the blocked user if
one exists. Returns the blocked user in the requested format when
successful.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->blocks->create('usertoblock');
```

### Removing a block

`destroy()` un-blocks the user specified in the `id` parameter for the
authenticating user. Returns the un-blocked user in the requested format
when successful.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->blocks->destroy('blockeduser');
```

### Who are you blocking (identifiers only)

`ids()` returns an array of user identifiers that the authenticating
user is blocking.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->blocks->ids();
```

### Who are you blocking

`list()` returns an array of user objects that the authenticating user
is blocking.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->blocks->list();
```

## Direct Message Methods

### Retrieving recent direct messages received

`messages()` returns a list of the 20 most recent direct messages sent
to the authenticating user.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->directMessages->messages();
```

The `message()` method accepts an array of optional parameters to modify
the query.

- `since_id` narrows the returned results to just those statuses
  after the specified identifier (up to 24 hours old).
- `max_id` narrows the returned results to just those statuses
  earlier than the specified identifier.
- `count` specifies the number of statuses to return, up to 200.
- `skip_status`, when set to boolean true, "t", or 1 will skip
  including a user's most recent status in the results.
- `include_entities` controls whether or not entities, which includes
  URLs, mentioned users, and hashtags, will be returned.

### Retrieving recent direct messages sent

`sent()` returns a list of the 20 most recent direct messages sent by
the authenticating user.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->directMessages->sent();
```

The `sent()` method accepts an array of optional parameters to modify
the query.

- `count` specifies the number of statuses to return, up to 20.
- `page` specifies the page of results to return, based on the `count`
  provided.
- `since_id` narrows the returned results to just those statuses
  after the specified identifier (up to 24 hours old).
- `max_id` narrows the returned results to just those statuses
  earlier than the specified identifier.
- `include_entities` controls whether or not entities, which includes
  URLs, mentioned users, and hashtags, will be returned.

### Sending a direct message

`new()` sends a new direct message to the specified user from the
authenticating user. Requires both the user and text parameters below.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->directMessages->new('myfriend', 'mymessage');
```

### Deleting a direct message

`destroy()` destroys the direct message specified in the required `id`
parameter. The authenticating user must be the recipient of the
specified direct message.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->directMessages->destroy(123548);
```

## Favorites Methods

### Retrieving favorites

`list()` returns the 20 most recent favorite statuses for the
authenticating user or user specified by the `id` parameter.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->favorites->list();
```

The `list()` method accepts an array of optional parameters to modify
the query.

- `user_id` specifies the ID of the user for whom to return the
  timeline.
- `screen_name` specifies the screen name of the user for whom to
  return the timeline.
- `since_id` narrows the returned results to just those statuses
  after the specified identifier (up to 24 hours old).
- `max_id` narrows the returned results to just those statuses
  earlier than the specified identifier.
- `count` specifies the number of statuses to return, up to 200.
- `include_entities` controls whether or not entities, which includes
  URLs, mentioned users, and hashtags, will be returned.

### Creating favorites

`create()` favorites the status specified in the `id` parameter as the
authenticating user.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->favorites->create(12351);
```

### Deleting a favorite

`destroy()` un-favorites the status specified in the `id` parameter as
the authenticating user.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->favorites->destroy(12351);
```

## Friendship Methods

### Creating a friend

`create()` befriends the user specified in the `id` parameter with the
authenticating user.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->friendships->create('mynewfriend');
```

### Deleting a friend

`destroy()` discontinues friendship with the user specified in the `id`
parameter and the authenticating user.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->friendships->destroy('myoldfriend');
```

## Search Methods

### Searching for tweets

`tweets()` returns a list of tweets matching the criteria specified in
`$query`. By default, 15 will be returned, but this value may be
changed using the `count` option.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->search->tweets('#zendframework');
```

The `tweets()` method accepts an optional second argument, array of
optional parameters to modify the query.

- `since_id` narrows the returned results to just those statuses
  after the specified identifier (up to 24 hours old).
- `max_id` narrows the returned results to just those statuses
  earlier than the specified identifier.
- `count` specifies the number of statuses to return, up to 200.
- `include_entities` controls whether or not entities, which includes
  URLs, mentioned users, and hashtags, will be returned.
- `lang` indicates which two-letter language code to restrict results
  to.
- `locale` indicates which two-letter language code is being used in
  the query.
- `geocode` can be used to indicate the geographical radius in which
  tweets should originate; the string should be in the form
  "latitude,longitude,radius", with "radius" being a unit followed by
  one of "mi" or "km".
- `result_type` indicates what type of results to retrieve, and
  should be one of "mixed," "recent," or "popular."
- `until` can be used to specify a the latest date for which to
  return tweets.

## Status Methods

### Retrieving the public timeline

`sample()` returns the 20 most recent statuses from non-protected users
with a custom user icon. The public timeline is cached by Twitter for 60
seconds.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->statuses->sample();
```

### Retrieving the home timeline

`homeTimeline()` returns the 20 most recent statuses posted by the
authenticating user and that user's friends.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->statuses->homeTimeline();
```

The `homeTimeline()` method accepts an array of optional parameters to
modify the query.

- `since_id` narrows the returned results to just those statuses
  after the specified identifier (up to 24 hours old).
- `max_id` narrows the returned results to just those statuses
  earlier than the specified identifier.
- `count` specifies the number of statuses to return, up to 200.
- `trim_user`, when set to boolean true, "t", or 1, will list the
  author identifier only in embedded user objects in the statuses
  returned.
- `contributor_details`, when set to boolean true, will return the
  screen name of any contributors to a status (instead of only the
  contributor identifier).
- `include_entities` controls whether or not entities, which includes
  URLs, mentioned users, and hashtags, will be returned.
- `exclude_replies` controls whether or not status updates that are
  in reply to other statuses will be returned.

### Retrieving the user timeline

`userTimeline()` returns the 20 most recent statuses posted from the
authenticating user.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->statuses->userTimeline();
```

The `userTimeline()` method accepts an array of optional parameters to
modify the query.

- `user_id` specifies the ID of the user for whom to return the
  timeline.
- `screen_name` specifies the screen name of the user for whom to
  return the timeline.
- `since_id` narrows the returned results to just those statuses
  after the specified identifier (up to 24 hours old).
- `max_id` narrows the returned results to just those statuses
  earlier than the specified identifier.
- `count` specifies the number of statuses to return, up to 200.
- `trim_user`, when set to boolean true, "t", or 1, will list the
  author identifier only in embedded user objects in the statuses
  returned.
- `contributor_details`, when set to boolean true, will return the
  screen name of any contributors to a status (instead of only the
  contributor identifier).
- `include_rts` controls whether or not to include native retweets in
  the returned list.
- `exclude_replies` controls whether or not status updates that are
  in reply to other statuses will be returned.

### Showing user status

`show()` returns a single status, specified by the `id` parameter below.
The status' author will be returned inline.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->statuses->show(1234);
```

### Updating user status

`update()` updates the authenticating user's status. This method
requires that you pass in the status update that you want to post to
Twitter.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->statuses->update('My Great Tweet');
```

The `update()` method accepts a second additional parameter.

`inReplyTo_StatusId` specifies the ID of an existing status that
the status to be posted is in reply to.

### Showing user replies

`mentionsTimeline()` returns the 20 most recent @replies (status updates
prefixed with @username) for the authenticating user.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->statuses->mentionsTimeline();
```

The `mentionsTimeline()` method accepts an array of optional parameters
to modify the query.

- `since_id` narrows the returned results to just those statuses
  after the specified identifier (up to 24 hours old).
- `max_id` narrows the returned results to just those statuses
  earlier than the specified identifier.
- `count` specifies the number of statuses to return, up to 200.
- `trim_user`, when set to boolean true, "t", or 1, will list the
  author identifier only in embedded user objects in the statuses
  returned.
- `contributor_details`, when set to boolean true, will return the
  screen name of any contributors to a status (instead of only the
  contributor identifier).
- `include_entities` controls whether or not entities, which includes
  URLs, mentioned users, and hashtags, will be returned.

### Deleting user status

`destroy()` destroys the status specified by the required `id`
parameter.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->statuses->destroy(12345);
```

## User Methods

### Showing user information

`show()` returns extended information of a given user, specified by ID
or screen name as per the required `id` parameter below.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->users->show('myfriend');
```

### Searching for users

`search()` will search for users matching the query provided.

```php
$twitter  = new Laminas\Twitter\Twitter($options);
$response = $twitter->users->search('Laminas');
```

The `search()` method accepts an array of optional parameters to modify
the query.

- `count` specifies the number of statuses to return, up to 20.
- `page` specifies the page of results to return, based on the `count`
  provided.
- `include_entities` controls whether or not entities, which includes
  URLs, mentioned users, and hashtags, will be returned.
