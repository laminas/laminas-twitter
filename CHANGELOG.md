# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 3.0.1 - 2017-08-17

### Added

- [zendframework/zendservice-twitter#46](https://github.com/zendframework/ZendService_Twitter/pull/46) adds
  the method `listsMembers()`, for retrieving a list of Twitter users associated
  with a given list. The signature is:

  ```php
  public function listMembers(
      int|string $listOrSlug,
      array $params = []
  ) : Response
  ```

  If `$listOrSlug` is a list identifier, no additional `$params` are required.
  If it is a string list slug, then one of the following `$params` MUST be
  present:

  - `owner_id`: a valid user identifier (integer)
  - `owner_screen_name`: a valid user screen name (string)

- [zendframework/zendservice-twitter#45](https://github.com/zendframework/ZendService_Twitter/pull/45) adds
  the ability to pass arrays of user identifiers OR screen names to each of the
  `usersLookup()` and `friendshipsLookup()` methods, giving them parity with the
  Twitter API. In each case, if you pass an array of values, the MUST be all
  user identifiers OR user screen names; you cannot mix the types.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 3.0.0 - 2017-08-15

### Added

- [zendframework/zendservice-twitter#39](https://github.com/zendframework/ZendService_Twitter/pull/39) adds
  support for PHP 7.1 and PHP 7.2.

- [zendframework/zendservice-twitter#34](https://github.com/zendframework/ZendService_Twitter/pull/34) adds
  support for uploading media, via the new classes `Laminas\Twitter\Media`,
  `Image`, and `Video`. In each case, instantiate the appropriate class with the
  path to the image on the filesystem and the appropriate media type, and then
  call `upload()` with a laminas-http client. The response will contain a
  `media_id` property, which you can then provide via the `media_ids` attribute
  when posting a status.

  ```php
  $image = new Image('data/logo.png', 'image/png');
  $response = $image->upload($client);
  
  $twitter->statusUpdate(
      'A post with an image',
      null,
      ['media_ids' => [$response->media_id]]
  );
  ```

- [zendframework/zendservice-twitter#42](https://github.com/zendframework/ZendService_Twitter/pull/42) adds
  support for attaching media to direct messages. To do so, first upload an
  image, marking the image for a direct message; you can also optionally mark it
  to _share_, in which case you can re-use the returned media identifier with
  multiple direct messages:

  ```php
  $image = new Image(
      'data/logo.png',
      'image/png',
      $forDirectMessage = true,
      $shared = false
  );
  $upload = $image->upload($client);
  ```

  Once you have the media identifier, you can provide it via an extra parameter
  to the `directMessagesNew()` method, or the new `directMessagesEventNew()`
  method (which more closely corresponds to the API endpoint):

  ```php
  $twitter->directmessagesEventsNew(
      $user,
      $message,
      ['media_id' => $upload->id_str]
  );
  ```

  Direct messages only support one attachment at a time.

- [zendframework/zendservice-twitter#37](https://github.com/zendframework/ZendService_Twitter/pull/37) and
  [zendframework/zendservice-twitter#43](https://github.com/zendframework/ZendService_Twitter/pull/43) add
  support for returning media entities when calling `statusesShow()`. The method
  now allows a second, optional argument, `$options`, which may contain the
  following keys to pass to the Twitter API:

  - `tweet_mode`; if present, it will be passed as the value `extended`.
  - `include_entities`
  - `trim_user`
  - `include_my_retweet`

- [zendframework/zendservice-twitter#34](https://github.com/zendframework/ZendService_Twitter/pull/34) adds
  support for Twitter's rate limit headers. Returned responses allow you to
  query them via `getRateLimit()`, and the returned
  `Laminas\Twitter\RateLimit` instance allows you to check the limit,
  remaining requests, and time to reset.

  ```php
  $response = $twitter->statusUpdate('A post');
  $rateLimit = $response->getRateLimit();
  if ($rateLimit->remaining === 0) {
      // Time to back off!
      sleep($rateLimit->reset); // seconds left until reset
  }
  ```

- [zendframework/zendservice-twitter#29](https://github.com/zendframework/ZendService_Twitter/pull/29) adds
  support for:
  - retrieving a list of follower identifiers (`/followers/ids` API)
  - retrieving the lists to which the user belongs (`/lists/memberships` API)
  - retrieving the friends of the user (`/friendships/lookup` API)
  - retrieving full user data on a list of users (`/users/lookup` API)
  - retrieving the identifiers for friends of the user (`/friends/ids` API)

- [zendframework/zendservice-twitter#31](https://github.com/zendframework/ZendService_Twitter/pull/31) adds
  support for PHP 7.

### Changed

- [zendframework/zendservice-twitter#44](https://github.com/zendframework/ZendService_Twitter/pull/44) modifies
  the visibility of both `get()` and `post()` to make them public; additionally,
  each also now initializes the request, and returns a
  `Laminas\Twitter\Response` instance, allowing them to be used to access
  API endpoints not yet directly supported in the `Twitter` class. As examples:

  ```php
  // Retrieve a list of friends for a user:
  $response = $twitter->get('friends/list', ['screen_name' => 'laminasdevteam']);
  foreach ($response->users as $user) {
      printf("- %s (%s)\n", $user->name, $user->screen_name);
  }

  // Add a tweet to a collection:
  $response = $twitter->post('collections/entries/add', [
      'id' => $collectionId,
      'tweet_id' => $statusId,
  ]);
  if ($response->isError()) {
      echo "Error adding tweet to collection:\n";
      foreach ($response->response->errors as $error) {
          printf("- %s: %s\n", $error->change->tweet_id, $error->reason);
      }
  }
  ```

- [zendframework/zendservice-twitter#41](https://github.com/zendframework/ZendService_Twitter/pull/41) modifies
  how the `Twitter` class sends `POST` requests to send JSON payloads instead of
  `application/x-www-form-urlencoded`. All payloads except those for media
  uploads support this, and several newer endpoints (such as methods for
  allowing direct message media attachments) now require them.

- [zendframework/zendservice-twitter#40](https://github.com/zendframework/ZendService_Twitter/pull/40) updates
  direct message support to set the character limit to 10k, as documented
  currently for the Twitter API.

- [zendframework/zendservice-twitter#34](https://github.com/zendframework/ZendService_Twitter/pull/34) updates
  the `Twitter` class to return a `Laminas\Twitter\Response` instance
  instead of a laminas-http response instance. This allows auto-parsing of the
  returned JSON response, as well as access to rate limit information.

### Deprecated

- Nothing.

### Removed

- [zendframework/zendservice-twitter#39](https://github.com/zendframework/ZendService_Twitter/pull/39) removes
  support for PHP versions less than 7.1.

### Fixed

- Nothing.
