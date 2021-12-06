<?php

namespace LaminasTest\Twitter;

use Laminas\Http;
use Laminas\Http\Client\Adapter\AdapterInterface;
use Laminas\Http\Client\Adapter\Curl as CurlAdapter;
use Laminas\OAuth\Client as OAuthClient;
use Laminas\OAuth\Consumer as OAuthConsumer;
use Laminas\OAuth\Token\Access as AccessToken;
use Laminas\OAuth\Token\Request as RequestToken;
use Laminas\Twitter;
use Laminas\Twitter\Exception\ExceptionInterface;
use Laminas\Twitter\RateLimit;
use Laminas\Twitter\Response as TwitterResponse;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionProperty;
use stdClass;

use function array_merge;
use function file_get_contents;
use function implode;
use function in_array;
use function is_array;
use function json_encode;
use function rand;
use function range;
use function str_pad;
use function str_repeat;
use function str_replace;

final class TwitterTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $twitter            = new Twitter\Twitter();
        $reflectionProperty = new ReflectionProperty($twitter, 'jsonFlags');
        $reflectionProperty->setAccessible(true);
        $this->jsonFlags = $reflectionProperty->getValue($twitter);
    }

    public function stubHttpClientInitialization(): OAuthClient
    {
        $client = $this->prophesize(OAuthClient::class);
        $client->setHeaders(['Accept-Charset' => 'ISO-8859-1,utf-8'])->will([$client, 'reveal']);
        $client->resetParameters()->will([$client, 'reveal']);
        $client->clearCookies()->will([$client, 'reveal']);
        $client->getCookies()->willReturn([]);
        return $client->reveal();
    }

    public function testRateLimitHeaders(): void
    {
        $rateLimits = [
            'x-rate-limit-limit'     => rand(1, 100),
            'x-rate-limit-remaining' => rand(1, 100),
            'x-rate-limit-reset'     => rand(1, 100),
        ];

        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'users/show.json',
            Http\Request::METHOD_GET,
            'users.show.mwop.json',
            ['screen_name' => 'mwop'],
            $rateLimits
        ));

        $finalResponse = $twitter->users->show('mwop');

        $this->assertInstanceOf(TwitterResponse::class, $finalResponse);

        $rateLimit = $finalResponse->getRateLimit();
        $this->assertInstanceOf(RateLimit::class, $rateLimit);
        $this->assertSame($rateLimit->limit, $rateLimits['x-rate-limit-limit']);
        $this->assertSame($rateLimit->remaining, $rateLimits['x-rate-limit-remaining']);
        $this->assertSame($rateLimit->reset, $rateLimits['x-rate-limit-reset']);
    }

    /**
     * OAuth tests
     */
    public function testProvidingAccessTokenInOptionsSetsHttpClientFromAccessToken(): void
    {
        $client = $this->prophesize(OAuthClient::class);
        $token  = $this->prophesize(AccessToken::class);
        $token->getHttpClient([
            'token'   => $token->reveal(),
            'siteUrl' => 'https://api.twitter.com/oauth',
        ], 'https://api.twitter.com/oauth', [])->will([$client, 'reveal']);

        $twitter = new Twitter\Twitter(['accessToken' => $token->reveal(), 'opt1' => 'val1']);
        $this->assertSame($client->reveal(), $twitter->getHttpClient());
    }

    public function testNotAuthorisedWithoutToken(): void
    {
        $twitter = new Twitter\Twitter();
        $this->assertFalse($twitter->isAuthorised());
    }

    public function testChecksAuthenticatedStateBasedOnAvailabilityOfAccessTokenBasedClient(): void
    {
        $client = $this->prophesize(OAuthClient::class);
        $token  = $this->prophesize(AccessToken::class);
        $token->getHttpClient([
            'token'   => $token->reveal(),
            'siteUrl' => 'https://api.twitter.com/oauth',
        ], 'https://api.twitter.com/oauth', [])->will([$client, 'reveal']);

        $twitter = new Twitter\Twitter(['accessToken' => $token->reveal()]);
        $this->assertTrue($twitter->isAuthorised());
    }

    public function testRelaysMethodsToInternalOAuthInstance(): void
    {
        $oauth = $this->prophesize(OAuthConsumer::class);
        $oauth->getAccessToken([], Argument::type(RequestToken::class))->willReturn('foo');
        foreach (
            [
                'getRequestToken',
                'getRedirectUrl',
                'redirect',
                'getToken',
            ] as $method
        ) {
            $oauth->$method()->willReturn('foo');
        }

        $twitter = new Twitter\Twitter(['opt1' => 'val1'], $oauth->reveal());
        $this->assertEquals('foo', $twitter->getRequestToken());
        $this->assertEquals('foo', $twitter->getRedirectUrl());
        $this->assertEquals('foo', $twitter->redirect());
        $this->assertEquals('foo', $twitter->getAccessToken(
            [],
            $this->prophesize(RequestToken::class)->reveal()
        ));
        $this->assertEquals('foo', $twitter->getToken());
    }

    public function testResetsHttpClientOnReceiptOfAccessTokenToOauthClient(): void
    {
        $requestToken = $this->prophesize(RequestToken::class)->reveal();
        $oauth        = $this->prophesize(OAuthConsumer::class);
        $client       = $this->prophesize(OAuthClient::class);
        $accessToken  = $this->prophesize(AccessToken::class);

        $accessToken->getHttpClient([])->will([$client, 'reveal']);
        $oauth->getAccessToken([], $requestToken)->will([$accessToken, 'reveal']);
        $client->setHeaders(['Accept-Charset' => 'ISO-8859-1,utf-8'])->will([$client, 'reveal']);

        $twitter = new Twitter\Twitter([], $oauth->reveal());
        $twitter->getAccessToken([], $requestToken);
        $this->assertSame($client->reveal(), $twitter->getHttpClient());
    }

    public function testAuthorisationFailureWithUsernameAndNoAccessToken(): void
    {
        $this->expectException(ExceptionInterface::class);
        $twitter = new Twitter\Twitter(['username' => 'me']);
        $twitter->statusesPublicTimeline();
    }

    /**
     * @group Laminas-8218
     */
    public function testUserNameNotRequired(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'users/show.json',
            Http\Request::METHOD_GET,
            'users.show.mwop.json',
            ['screen_name' => 'mwop']
        ));
        $response = $twitter->users->show('mwop');
        $this->assertInstanceOf(TwitterResponse::class, $response);
        $exists = $response->id !== null;
        $this->assertTrue($exists);
    }

    /**
     * @group Laminas-7781
     */
    public function testRetrievingStatusesWithValidScreenNameThrowsNoInvalidScreenNameException(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/user_timeline.json',
            Http\Request::METHOD_GET,
            'statuses.user_timeline.mwop.json',
            ['screen_name' => 'mwop']
        ));
        $twitter->statuses->userTimeline(['screen_name' => 'mwop']);
    }

    /**
     * @group Laminas-7781
     */
    public function testRetrievingStatusesWithInvalidScreenNameCharacterThrowsInvalidScreenNameException(): void
    {
        $this->expectException(ExceptionInterface::class);
        $twitter = new Twitter\Twitter();
        $twitter->statuses->userTimeline(['screen_name' => 'abc.def']);
    }

    /**
     * @group Laminas-7781
     */
    public function testRetrievingStatusesWithInvalidScreenNameLengthThrowsInvalidScreenNameException(): void
    {
        $this->expectException(ExceptionInterface::class);
        $twitter = new Twitter\Twitter();
        $twitter->statuses->userTimeline(['screen_name' => 'abcdef_abc123_abc123x']);
    }

    /**
     * @group Laminas-7781
     */
    public function testStatusUserTimelineConstructsExpectedGetUriAndOmitsInvalidParams(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/user_timeline.json',
            Http\Request::METHOD_GET,
            'statuses.user_timeline.mwop.json',
            [
                'count'       => '123',
                'user_id'     => 783214,
                'since_id'    => '10000',
                'max_id'      => '20000',
                'screen_name' => 'twitter',
                'tweet_mode'  => 'extended',
            ]
        ));
        $twitter->statuses->userTimeline([
            'id'          => '783214',
            'since'       => '+2 days', /* invalid param since Apr 2009 */
            'page'        => '1',
            'count'       => '123',
            'user_id'     => '783214',
            'since_id'    => '10000',
            'max_id'      => '20000',
            'screen_name' => 'twitter',
            'tweet_mode'  => 'extended',
        ]);
    }

    public function testOverloadingGetShouldReturnObjectInstanceWithValidMethodType(): void
    {
        $twitter = new Twitter\Twitter();
        $return  = $twitter->statuses;
        $this->assertSame($twitter, $return);
    }

    public function testOverloadingGetShouldthrowExceptionWithInvalidMethodType(): void
    {
        $this->expectException(ExceptionInterface::class);
        $twitter = new Twitter\Twitter();
        $twitter->foo;
    }

    public function testOverloadingGetShouldthrowExceptionWithInvalidFunction(): void
    {
        $this->expectException(ExceptionInterface::class);
        $twitter = new Twitter\Twitter();
        $return  = $twitter->foo();
    }

    public function testMethodProxyingDoesNotThrowExceptionsWithValidMethods(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/sample.json',
            Http\Request::METHOD_GET,
            'statuses.sample.json',
            []
        ));
        $twitter->statuses->sample();
    }

    public function testMethodProxyingThrowExceptionsWithInvalidMethods(): void
    {
        $this->expectException(ExceptionInterface::class);
        $twitter = new Twitter\Twitter();
        $twitter->statuses->foo();
    }

    public function testVerifiedCredentials(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'account/verify_credentials.json',
            Http\Request::METHOD_GET,
            'account.verify_credentials.json',
            []
        ));
        $response = $twitter->account->verifyCredentials();
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testSampleTimelineStatusReturnsResults(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/sample.json',
            Http\Request::METHOD_GET,
            'statuses.sample.json',
            []
        ));
        $response = $twitter->statuses->sample();
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testRateLimitStatusReturnsResults(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'application/rate_limit_status.json',
            Http\Request::METHOD_GET,
            'application.rate_limit_status.json',
            []
        ));
        $response = $twitter->application->rateLimitStatus();
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testRateLimitStatusHasHitsLeft(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'application/rate_limit_status.json',
            Http\Request::METHOD_GET,
            'application.rate_limit_status.json',
            []
        ));
        $response = $twitter->application->rateLimitStatus();
        $status   = $response->toValue();
        $this->assertEquals(180, $status->resources->statuses->{'/statuses/user_timeline'}->remaining);
    }

    /**
     * TODO: Check actual purpose. New friend returns XML response, existing
     * friend returns a 403 code.
     */
    public function testFriendshipCreate(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'friendships/create.json',
            Http\Request::METHOD_POST,
            'friendships.create.twitter.json',
            ['screen_name' => 'twitter']
        ));
        $response = $twitter->friendships->create('twitter');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testHomeTimelineWithCountReturnsResults(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/home_timeline.json',
            Http\Request::METHOD_GET,
            'statuses.home_timeline.page.json',
            ['count' => 3]
        ));
        $response = $twitter->statuses->homeTimeline(['count' => 3]);
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testHomeTimelineWithExtendedModeReturnsResults(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/home_timeline.json',
            Http\Request::METHOD_GET,
            'statuses.home_timeline.page.json',
            ['tweet_mode' => 'extended']
        ));
        $response = $twitter->statuses->homeTimeline(['tweet_mode' => 'extended']);
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    /**
     * TODO: Add verification for ALL optional parameters
     */
    public function testUserTimelineReturnsResults(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/user_timeline.json',
            Http\Request::METHOD_GET,
            'statuses.user_timeline.mwop.json',
            ['screen_name' => 'mwop']
        ));
        $response = $twitter->statuses->userTimeline(['screen_name' => 'mwop']);
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    /**
     * TODO: Add verification for ALL optional parameters
     */
    public function testPostStatusUpdateReturnsResponse(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/update.json',
            Http\Request::METHOD_POST,
            'statuses.update.json',
            ['status' => 'Test Message 1']
        ));
        $response = $twitter->statuses->update('Test Message 1');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testPostStatusUpdateToLongShouldThrowException(): void
    {
        $this->expectException(ExceptionInterface::class);
        $twitter = new Twitter\Twitter();
        $twitter->statuses->update('Test Message - ' . str_repeat(' Hello ', 140));
    }

    public function testStatusUpdateShouldAllow280CharactersOfUTF8Encoding(): void
    {
        $message = str_repeat('é', 280);
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/update.json',
            Http\Request::METHOD_POST,
            'statuses.update.json',
            ['status' => $message]
        ));
        $response = $twitter->statuses->update($message);
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testPostStatusUpdateEmptyShouldThrowException(): void
    {
        $this->expectException(ExceptionInterface::class);
        $twitter = new Twitter\Twitter();
        $twitter->statuses->update('');
    }

    public function testShowStatusReturnsResponse(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/show/307529814640840705.json',
            Http\Request::METHOD_GET,
            'statuses.show.json',
            []
        ));
        $response = $twitter->statuses->show('307529814640840705');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testCreateFavoriteStatusReturnsResponse(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'favorites/create.json',
            Http\Request::METHOD_POST,
            'favorites.create.json',
            ['id' => 15042159587]
        ));
        $response = $twitter->favorites->create(15042159587);
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testFavoritesListReturnsResponse(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'favorites/list.json',
            Http\Request::METHOD_GET,
            'favorites.list.json',
            []
        ));
        $response = $twitter->favorites->list();
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testDestroyFavoriteReturnsResponse(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'favorites/destroy.json',
            Http\Request::METHOD_POST,
            'favorites.destroy.json',
            ['id' => 15042159587]
        ));
        $response = $twitter->favorites->destroy(15042159587);
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testStatusDestroyReturnsResult(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/destroy/15042159587.json',
            Http\Request::METHOD_POST,
            'statuses.destroy.json'
        ));
        $response = $twitter->statuses->destroy(15042159587);
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testStatusHomeTimelineWithNoOptionsReturnsResults(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/home_timeline.json',
            Http\Request::METHOD_GET,
            'statuses.home_timeline.page.json',
            []
        ));
        $response = $twitter->statuses->homeTimeline();
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testUserShowByIdReturnsResults(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'users/show.json',
            Http\Request::METHOD_GET,
            'users.show.mwop.json',
            ['screen_name' => 'mwop']
        ));
        $response = $twitter->users->show('mwop');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    /**
     * TODO: Add verification for ALL optional parameters
     *
     * @todo rename to "mentions_timeline"
     */
    public function testStatusMentionsReturnsResults(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/mentions_timeline.json',
            Http\Request::METHOD_GET,
            'statuses.mentions_timeline.json',
            []
        ));
        $response = $twitter->statuses->mentionsTimeline();
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testMentionsTimelineWithExtendedModeReturnsResults(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/mentions_timeline.json',
            Http\Request::METHOD_GET,
            'statuses.mentions_timeline.json',
            ['tweet_mode' => 'extended']
        ));
        $response = $twitter->statuses->mentionsTimeline(['tweet_mode' => 'extended']);
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    /**
     * TODO: Add verification for ALL optional parameters
     */
    public function testFriendshipDestroy(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'friendships/destroy.json',
            Http\Request::METHOD_POST,
            'friendships.destroy.twitter.json',
            ['screen_name' => 'twitter']
        ));
        $response = $twitter->friendships->destroy('twitter');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testBlockingCreate(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'blocks/create.json',
            Http\Request::METHOD_POST,
            'blocks.create.twitter.json',
            ['screen_name' => 'twitter']
        ));
        $response = $twitter->blocks->create('twitter');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testBlockingList(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'blocks/list.json',
            Http\Request::METHOD_GET,
            'blocks.list.json',
            ['cursor' => -1]
        ));
        $response = $twitter->blocks->list();
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testUsersShowAcceptsScreenNamesWithNumbers(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient(
            $this->stubOAuthClient(
                'users/show.json',
                Http\Request::METHOD_GET,
                'users.show.JuicyBurger661.json',
                ['screen_name' => 'JuicyBurger661']
            )
        );
        // $id as screen_name with numbers
        $twitter->users->show('JuicyBurger661');
    }

    public function testUsersShowAcceptsIdAsStringArgument(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient(
            $this->stubOAuthClient(
                'users/show.json',
                Http\Request::METHOD_GET,
                'users.show.mwop.json',
                ['user_id' => 9453382]
            )
        );
        // $id as string
        $twitter->users->show('9453382');
    }

    public function testUsersShowAcceptsIdAsIntegerArgument(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient(
            $this->stubOAuthClient(
                'users/show.json',
                Http\Request::METHOD_GET,
                'users.show.mwop.json',
                ['user_id' => 9453382]
            )
        );
        // $id as integer
        $twitter->users->show(9453382);
    }

    public function testBlockingIds(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'blocks/ids.json',
            Http\Request::METHOD_GET,
            'blocks.ids.json',
            ['cursor' => -1]
        ));
        $response = $twitter->blocks->ids();
        $this->assertInstanceOf(TwitterResponse::class, $response);
        $this->assertContains('23836616', $response->ids);
    }

    public function testBlockingDestroy(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'blocks/destroy.json',
            Http\Request::METHOD_POST,
            'blocks.destroy.twitter.json',
            ['screen_name' => 'twitter']
        ));
        $response = $twitter->blocks->destroy('twitter');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    /**
     * @group Laminas-6284
     */
    public function testTwitterObjectsSoNotShareSameHttpClientToPreventConflictingAuthentication(): void
    {
        $twitter1 = new Twitter\Twitter(['username' => 'laminastestuser1']);
        $twitter2 = new Twitter\Twitter(['username' => 'laminastestuser2']);
        $this->assertNotSame($twitter1->getHttpClient(), $twitter2->getHttpClient());
    }

    public function testSearchTweets(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'search/tweets.json',
            Http\Request::METHOD_GET,
            'search.tweets.json',
            ['q' => '#laminas']
        ));
        $response = $twitter->search->tweets('#laminas');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testUsersSearch(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'users/search.json',
            Http\Request::METHOD_GET,
            'users.search.json',
            ['q' => 'Laminas']
        ));
        $response = $twitter->users->search('Laminas');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testListsSubscribers(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'lists/subscribers.json',
            Http\Request::METHOD_GET,
            'lists.subscribers.json',
            ['screen_name' => 'devzone']
        ));
        $response = $twitter->lists->subscribers('devzone');
        $this->assertInstanceOf(TwitterResponse::class, $response);
        $payload = $response->toValue();
        $this->assertCount(1, $payload->users);
        $this->assertEquals(4795561, $payload->users[0]->id);
    }

    public function testFriendsIds(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'users/search.json',
            Http\Request::METHOD_GET,
            'users.search.json',
            ['q' => 'Laminas']
        ));
        $response = $twitter->users->search('Laminas');
        $this->assertInstanceOf(TwitterResponse::class, $response);
        $payload = $response->toValue();
        $this->assertCount(20, $payload);
        $this->assertEquals(15012215, $payload[0]->id);
    }

    /**
     * @psalm-return array<array-key, array{
     *     0: array<string, array<string, mixed>>,
     *     1: AdapterInterface
     * }>
     */
    public function providerAdapterAlwaysReachableIfSpecifiedConfiguration(): array
    {
        $curl = new CurlAdapter();

        return [
            [
                [
                    'http_client_options' => [
                        'adapter' => $curl,
                    ],
                ],
                $curl,
            ],
            [
                [
                    'access_token'        => [
                        'token'  => 'some_token',
                        'secret' => 'some_secret',
                    ],
                    'http_client_options' => [
                        'adapter' => $curl,
                    ],
                ],
                $curl,
            ],
            [
                [
                    'access_token'        => [
                        'token'  => 'some_token',
                        'secret' => 'some_secret',
                    ],
                    'oauth_options'       => [
                        'consumerKey'    => 'some_consumer_key',
                        'consumerSecret' => 'some_consumer_secret',
                    ],
                    'http_client_options' => [
                        'adapter' => $curl,
                    ],
                ],
                $curl,
            ],
        ];
    }

    /**
     * @dataProvider providerAdapterAlwaysReachableIfSpecifiedConfiguration
     */
    public function testAdapterAlwaysReachableIfSpecified(array $config, AdapterInterface $adapter): void
    {
        $twitter = new Twitter\Twitter($config);
        $this->assertSame($adapter, $twitter->getHttpClient()->getAdapter());
    }

    public function testDirectMessagesNewRaisesExceptionForEmptyMessage(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubHttpClientInitialization());
        $this->expectException(Twitter\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one char');
        $twitter->directMessagesNew('twitter', '');
    }

    public function testDirectMessagesNewRaisesExceptionForTooLongOfMessage(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubHttpClientInitialization());
        $text = str_pad('', 10001, 'X');
        $this->expectException(Twitter\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('no more than 10000 char');
        $twitter->directMessagesNew('twitter', $text);
    }

    public function testDirectMessageUsesEventsApi(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'direct_messages/events/new.json',
            Http\Request::METHOD_POST,
            'direct_messages.events.new.json',
            [
                'event' => [
                    'type'           => 'message_create',
                    'message_create' => [
                        'target'       => [
                            'recipient_id' => '1',
                        ],
                        'message_data' => [
                            'text' => 'Message',
                        ],
                    ],
                ],
            ]
        ));
        $response = $twitter->directMessages->new('1', 'Message');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testDirectMessageUsingScreenNameResultsInUserLookup(): void
    {
        $twitter = new Twitter\Twitter();
        $client  = $this->prophesize(OAuthClient::class);
        $client->resetParameters()->will([$client, 'reveal']);
        $client->setHeaders(['Accept-Charset' => 'ISO-8859-1,utf-8'])->will([$client, 'reveal']);
        $client->clearCookies()->will([$client, 'reveal']);
        $client->getCookies()->willReturn([]);
        $client->setCookies([])->willReturn([]);

        $client->setUri('https://api.twitter.com/1.1/users/show.json')->shouldBeCalled();
        $client->setMethod('GET')->will([$client, 'reveal']);
        $client->setParameterGet(['screen_name' => 'Laminas'])->shouldBeCalled();

        $userResponse = $this->prophesize(Http\Response::class);
        $userResponse->getBody()->willReturn('{"id_str":"1"}');
        $userResponse->getHeaders()->willReturn(null);
        $userResponse->isSuccess()->willReturn(true);

        $headers = $this->prophesize(Http\Headers::class);
        $headers->addHeaderLine('Content-Type', 'application/json')->shouldBeCalled();
        $request = $this->prophesize(Http\Request::class);
        $request->getHeaders()->will([$headers, 'reveal']);
        $client->getRequest()->will([$request, 'reveal']);

        $data = [
            'event' => [
                'type'           => 'message_create',
                'message_create' => [
                    'target'       => [
                        'recipient_id' => '1',
                    ],
                    'message_data' => [
                        'text' => 'Message',
                    ],
                ],
            ],
        ];
        $client->setUri('https://api.twitter.com/1.1/direct_messages/events/new.json')->shouldBeCalled();
        $client->setMethod('POST')->will([$client, 'reveal']);
        $client->setRawBody(json_encode($data, $this->jsonFlags))->shouldBeCalled();

        $dmResponse = $this->prophesize(Http\Response::class);
        $dmResponse->getBody()->willReturn(file_get_contents(__DIR__ . '/_files/direct_messages.events.new.json'));
        $dmResponse->getHeaders()->willReturn(null);

        $client->send()->willReturn(
            $userResponse->reveal(),
            $dmResponse->reveal()
        );

        $twitter->setHttpClient($client->reveal());
        $response = $twitter->directMessages->new('Laminas', 'Message');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testDirectMessageWithInvalidScreenNameResultsInException(): void
    {
        $twitter = new Twitter\Twitter();
        $client  = $this->prophesize(OAuthClient::class);
        $client->resetParameters()->will([$client, 'reveal']);
        $client->setHeaders(['Accept-Charset' => 'ISO-8859-1,utf-8'])->will([$client, 'reveal']);
        $client->clearCookies()->will([$client, 'reveal']);
        $client->getCookies()->willReturn([]);
        $client->setCookies([])->willReturn([]);

        $client->setUri('https://api.twitter.com/1.1/users/show.json')->shouldBeCalled();
        $client->setMethod('GET')->will([$client, 'reveal']);
        $client->setParameterGet(['screen_name' => 'Laminas'])->shouldBeCalled();

        $userResponse = $this->prophesize(Http\Response::class);
        $userResponse->getBody()->willReturn('{"id_str":"1"}');
        $userResponse->getHeaders()->willReturn(null);
        $userResponse->isSuccess()->willReturn(false);

        $client->getRequest()->shouldNotBeCalled();

        $client->setUri('https://api.twitter.com/1.1/direct_messages/events/new.json')->shouldNotBeCalled();
        $client->setMethod('POST')->shouldNotBeCalled();

        $client->send()->willReturn($userResponse->reveal());

        $twitter->setHttpClient($client->reveal());
        $this->expectException(Twitter\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid user');
        $twitter->directMessages->new('Laminas', 'Message');
    }

    public function testDirectMessageWithUserIdentifierSkipsUserLookup(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'direct_messages/events/new.json',
            Http\Request::METHOD_POST,
            'direct_messages.events.new.json',
            [
                'event' => [
                    'type'           => 'message_create',
                    'message_create' => [
                        'target'       => [
                            'recipient_id' => '1',
                        ],
                        'message_data' => [
                            'text' => 'Message',
                        ],
                    ],
                ],
            ]
        ));
        $response = $twitter->directMessages->new('1', 'Message');
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testDirectMessageAllowsProvidingMedia(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'direct_messages/events/new.json',
            Http\Request::METHOD_POST,
            'direct_messages.events.new.media.json',
            [
                'event' => [
                    'type'           => 'message_create',
                    'message_create' => [
                        'target'       => [
                            'recipient_id' => '1',
                        ],
                        'message_data' => [
                            'text'       => 'Message',
                            'attachment' => [
                                'type'  => 'media',
                                'media' => [
                                    'id' => 'XXXX',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        ));
        $response = $twitter->directMessages->new('1', 'Message', ['media_id' => 'XXXX']);
        $this->assertInstanceOf(TwitterResponse::class, $response);
    }

    public function testStatusesShowWillPassAdditionalOptionsWhenPresent(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'statuses/show/12345.json',
            Http\Request::METHOD_GET,
            'statuses.show.json',
            [
                'tweet_mode'         => 'extended',
                'include_entities'   => true,
                'trim_user'          => true,
                'include_my_retweet' => true,
            ]
        ));

        $finalResponse = $twitter->statuses->show(12345, [
            'tweet_mode'             => true,
            'include_entities'       => true,
            'trim_user'              => true,
            'include_my_retweet'     => true,
            'should_not_be_included' => true,
        ]);

        $this->assertInstanceOf(TwitterResponse::class, $finalResponse);
    }

    public function testListsMembersCanBeCalledWithListIdentifierOnly(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'lists/members.json',
            Http\Request::METHOD_GET,
            'lists.members.json',
            [
                'list_id' => 12345,
            ]
        ));

        $finalResponse = $twitter->lists->members(12345);
        $this->assertInstanceOf(TwitterResponse::class, $finalResponse);
    }

    public function testListsMembersCanBeCalledWithListSlugAndIntegerOwnerId(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'lists/members.json',
            Http\Request::METHOD_GET,
            'lists.members.json',
            [
                'slug'     => 'laminas',
                'owner_id' => 12345,
            ]
        ));

        $finalResponse = $twitter->lists->members('laminas', ['owner_id' => 12345]);
        $this->assertInstanceOf(TwitterResponse::class, $finalResponse);
    }

    public function testListsMembersCanBeCalledWithListSlugAndStringOwnerScreenName(): void
    {
        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'lists/members.json',
            Http\Request::METHOD_GET,
            'lists.members.json',
            [
                'slug'              => 'laminas',
                'owner_screen_name' => 'laminasdevteam',
            ]
        ));

        $finalResponse = $twitter->lists->members('laminas', ['owner_screen_name' => 'laminasdevteam']);
        $this->assertInstanceOf(TwitterResponse::class, $finalResponse);
    }

    public function testListsMembersRaisesExceptionIfSlugPassedWithoutOwnerInformation(): void
    {
        $twitter = new Twitter\Twitter();
        $this->expectException(Twitter\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing owner info');
        $twitter->lists->members('laminas');
    }

    public function invalidIntegerIdentifiers(): array
    {
        return [
            'null'       => [null],
            'true'       => [true],
            'false'      => [false],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'string'     => ['not-an-integer'],
            'array'      => [[1]],
            'object'     => [new stdClass()],
        ];
    }

    /**
     * @dataProvider invalidIntegerIdentifiers
     * @param mixed $ownerId
     */
    public function testListsMembersRaisesExceptionIfSlugPassedWithInvalidOwnerId($ownerId): void
    {
        $twitter = new Twitter\Twitter();
        $this->expectException(Twitter\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid owner_id');
        $twitter->lists->members('laminas', ['owner_id' => $ownerId]);
    }

    public function invalidStringIdentifiers(): array
    {
        return [
            'empty'         => [''],
            'too-long'      => [implode('', range('a', 'z'))],
            'invalid-chars' => ['this-is !inv@lid'],
        ];
    }

    /**
     * @dataProvider invalidStringIdentifiers
     */
    public function testListsMembersRaisesExceptionIfSlugPassedWithInvalidOwnerScreenName(string $owner): void
    {
        $twitter = new Twitter\Twitter();
        $this->expectException(Twitter\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid owner_screen_name');
        $twitter->lists->members('laminas', ['owner_screen_name' => $owner]);
    }

    /**
     * @psalm-return iterable<string, array{
     *     0: int|string|int[]|string[],
     *     1: string
     * }>
     */
    public function userIdentifierProvider(): iterable
    {
        yield 'single-user_id' => [111, 'user_id'];
        yield 'single-screen_name' => ['laminasdevteam', 'screen_name'];
        yield 'multi-user_id' => [range(100, 150), 'user_id'];
        yield 'multi-screen_name' => [range('a', 'z'), 'screen_name'];
    }

    /**
     * @dataProvider userIdentifierProvider
     * @param mixed $id
     */
    public function testUsersLookupAcceptsAllSupportedUserIdentifiers($id, string $paramKey): void
    {
        $expected       = is_array($id)
            ? $expected = implode(',', $id)
            : $id;

        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'users/lookup.json',
            Http\Request::METHOD_POST,
            'users.lookup.json',
            [$paramKey => $expected]
        ));

        $finalResponse = $twitter->users->lookup($id);
        $this->assertInstanceOf(TwitterResponse::class, $finalResponse);
    }

    /**
     * @dataProvider userIdentifierProvider
     * @param mixed $id
     */
    public function testFriendshipsLookupAcceptsAllSupportedUserIdentifiers($id, string $paramKey): void
    {
        $expected       = is_array($id)
            ? $expected = implode(',', $id)
            : $id;

        $twitter = new Twitter\Twitter();
        $twitter->setHttpClient($this->stubOAuthClient(
            'friendships/lookup.json',
            Http\Request::METHOD_GET,
            'friendships.lookup.json',
            [$paramKey => $expected]
        ));

        $finalResponse = $twitter->friendships->lookup($id);
        $this->assertInstanceOf(TwitterResponse::class, $finalResponse);
    }

    /**
     * @psalm-return iterable<string, array{
     *     0: mixed,
     *     1: string
     * }>
     */
    public function invalidUserIdentifierProvider(): iterable
    {
        yield 'null'                      => [null, 'integer or a string'];
        yield 'true'                      => [true, 'integer or a string'];
        yield 'false'                     => [false, 'integer or a string'];
        yield 'zero-float'                => [0.0, 'integer or a string'];
        yield 'float'                     => [1.1, 'integer or a string'];
        yield 'empty-string'              => ['', 'alphanumeric'];
        yield 'malformed-string'          => ['not a valid screen name', 'alphanumeric'];
        yield 'array-too-many'            => [range(1, 200), 'no more than 100'];
        yield 'array-type-mismatch'       => [[1, 'screenname'], 'identifiers OR screen names'];
        yield 'array-invalid-screen-name' => [['not a valid screen name'], 'alphanumeric'];
        yield 'object'                    => [new stdClass(), 'integer or a string'];
    }

    /**
     * @dataProvider invalidUserIdentifierProvider
     * @param mixed $ids
     */
    public function testUsersLookupRaisesExceptionIfInvalidIdentifiersProvided($ids, string $expectedMessage): void
    {
        $twitter = new Twitter\Twitter();

        $this->expectException(Twitter\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $twitter->users->lookup($ids);
    }

    /**
     * @dataProvider invalidUserIdentifierProvider
     * @param mixed $ids
     */
    public function testFriendshipsLookupRaisesExceptionIfInvalidIdentifiersProvided(
        $ids,
        string $expectedMessage
    ): void {
        $twitter = new Twitter\Twitter();

        $this->expectException(Twitter\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $twitter->friendships->lookup($ids);
    }

    public function validGeocodeOptions(): array
    {
        return [
            'mile-radius' => ['53.3242381,-6.3857848,1mi'],
            'km-radius'   => ['53.3242381,-6.3857848,1km'],
        ];
    }

    /**
     * @dataProvider validGeocodeOptions
     * @param string $geocode
     */
    public function testSearchTweetsShouldNotInvalidateCorrectlyFormattedGeocodeOption($geocode): void
    {
        $twitter = new Twitter\Twitter();

        $options = [
            'geocode' => $geocode,
        ];

        $twitter->setHttpClient($this->stubOAuthClient(
            'search/tweets.json',
            Http\Request::METHOD_GET,
            'search.tweets.json',
            array_merge($options, ['q' => 'foo'])
        ));

        $finalResponse = $twitter->search->tweets('foo', $options);
        $this->assertInstanceOf(TwitterResponse::class, $finalResponse);
    }

    public function invalidGeocodeOptions(): array
    {
        return [
            'too-few-commas'  => ['53.3242381,-6.3857848', 'latitude,longitude,radius'],
            'too-many-commas' => ['53.3242381,-6.3857848,1mi,why', 'latitude,longitude,radius'],
            'invalid-radius'  => ['53.3242381,-6.3857848,1', 'Radius segment'],
        ];
    }

    /**
     * @dataProvider invalidGeocodeOptions
     * @param string $geocode
     * @param string $expectedMessage
     */
    public function testSearchTweetsShouldInvalidateIncorrectlyFormattedGeocodeOption(
        $geocode,
        $expectedMessage
    ): void {
        $twitter = new Twitter\Twitter();

        $options = [
            'geocode' => $geocode,
        ];

        $this->expectException(Twitter\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        $twitter->search->tweets('foo', $options);
    }

    /**
     * Quick reusable OAuth client stub setup. Its purpose is to fake
     * HTTP interactions with Twitter so the component can focus on what matters:
     * 1. Makes correct requests (URI, parameters and HTTP method)
     * 2. Parses all responses and returns a TwitterResponse
     * 3. TODO: Correctly utilises all optional parameters
     *
     * If used correctly, tests will be fast, efficient, and focused on
     * Laminas_Service_Twitter's behaviour only. No other dependencies need be
     * tested. The Twitter API Changelog should be regularly reviewed to
     * ensure the component is synchronised to the API.
     *
     * @param string $path Path appended to Twitter API endpoint
     * @param string $method Do we expect HTTP GET or POST?
     * @param string $responseFile File containing a valid XML response to the request
     * @param array $params Expected GET/POST parameters for the request
     * @param array $responseHeaders Headers expected on the returned response
     * @return OAuthClient
     */
    private function stubOAuthClient(
        $path,
        $method,
        $responseFile = null,
        ?array $params = null,
        array $responseHeaders = []
    ) {
        $client = $this->prophesize(OAuthClient::class);
        $client->setMethod($method)->will([$client, 'reveal']);
        $client->resetParameters()->will([$client, 'reveal']);
        $client->setUri('https://api.twitter.com/1.1/' . $path)->shouldBeCalled();
        $client->setHeaders(['Accept-Charset' => 'ISO-8859-1,utf-8'])->will([$client, 'reveal']);
        $client->clearCookies()->will([$client, 'reveal']);
        $client->getCookies()->willReturn([]);

        if (null !== $params && $method === 'GET') {
            $client->setParameterGet($params)->shouldBeCalled();
        }

        if ($method === 'POST' && null !== $params) {
            $pathMinusExtension = str_replace('.json', '', $path);
            in_array($pathMinusExtension, Twitter\Twitter::PATHS_JSON_PAYLOAD, true)
                ? $this->prepareJsonPayloadForClient($client, $params)
                : $this->prepareFormEncodedPayloadForClient($client, $params);
        }

        $response = $this->prophesize(Http\Response::class);

        $response->getBody()->will(function () use ($responseFile) {
            if (null === $responseFile) {
                return '{}';
            }
            return file_get_contents(__DIR__ . '/_files/' . $responseFile);
        });

        $headers = $this->prophesize(Http\Headers::class);
        foreach ($responseHeaders as $headerName => $value) {
            $headers->has($headerName)->willReturn(true);
            $header = $this->prophesize(Http\Header\HeaderInterface::class);
            $header->getFieldValue()->willReturn($value);
            $headers->get($headerName)->will([$header, 'reveal']);
        }

        $response->getHeaders()->will([$headers, 'reveal']);

        $client->send()->will([$response, 'reveal']);

        return $client->reveal();
    }

    private function prepareJsonPayloadForClient(ObjectProphecy $client, ?array $params = null): void
    {
        $headers = $this->prophesize(Http\Headers::class);
        $headers->addHeaderLine('Content-Type', 'application/json')->shouldBeCalled();
        $request = $this->prophesize(Http\Request::class);
        $request->getHeaders()->will([$headers, 'reveal']);
        $client->getRequest()->will([$request, 'reveal']);

        $requestBody = json_encode($params, $this->jsonFlags);
        $client->setRawBody($requestBody)->shouldBeCalled();
    }

    private function prepareFormEncodedPayloadForClient(ObjectProphecy $client, ?array $params = null): void
    {
        $client->setParameterPost($params)->will([$client, 'reveal']);
    }
}
