<?php

/**
 * @see       https://github.com/laminas/laminas-twitter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-twitter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-twitter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Twitter;

use Laminas\Config;
use Laminas\Twitter;

/**
 * @category   Laminas
 * @package    Laminas_Service_Twitter
 * @subpackage UnitTests
 * @group      Laminas_Service
 * @group      Laminas_Service_Twitter
 */
class SearchTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Laminas\Twitter\Search $twitter
     */
    protected $twitter;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @return void
     */
    protected function setUp()
    {
        if (!defined('TESTS_LAMINAS_SERVICE_TWITTER_ONLINE_ENABLED')
            || !constant('TESTS_LAMINAS_SERVICE_TWITTER_ONLINE_ENABLED')
        ) {
            $this->markTestSkipped('Twitter tests are not enabled');
            return;
        }

        $this->twitter = new Twitter\Search();
    }

    public function testSetResponseTypeToJson()
    {
        $this->twitter->setResponseType('json');
        $this->assertEquals('json', $this->twitter->getResponseType());
    }

    public function testSetResponseTypeToAtom()
    {
        $this->twitter->setResponseType('atom');
        $this->assertEquals('atom', $this->twitter->getResponseType());
    }

    public function testInvalidResponseTypeShouldThrowException()
    {
        try {
            $this->twitter->setResponseType('xml');
            $this->fail('Setting an invalid response type should throw an exception');
        } catch (\Exception $e) {
            // ok
        }
    }

    public function testValidResponseTypeShouldNotThrowException()
    {
        $this->twitter->setResponseType('atom');
    }

    public function testSetOptionsWithArray()
    {
        $this->twitter->setOptions(array(
            'lang'        => 'fr',
            'result_type' => 'mixed',
            'show_user'   => true
        ));
        $this->assertEquals('Laminas\Twitter\SearchOptions', get_class($this->twitter->getOptions()));
        $this->assertEquals('fr', $this->twitter->getOptions()->getLanguage());
        $this->assertEquals('mixed', $this->twitter->getOptions()->getResultType());
    }

    public function testSetOptionsWithArrayLongName()
    {
        $this->twitter->setOptions(array(
            'language'         => 'fr',
            'results_per_page' => '10',
            'result_type'      => 'mixed',
            'show_user'        => true
        ));
        $this->assertEquals('Laminas\Twitter\SearchOptions', get_class($this->twitter->getOptions()));
        $this->assertEquals('fr', $this->twitter->getOptions()->getLanguage());
        $this->assertEquals('mixed', $this->twitter->getOptions()->getResultType());
    }

    public function testSetOptionsWithConfig()
    {
        $this->twitter->setOptions(new Config\Config(array(
            'lang'        => 'fr',
            'result_type' => 'mixed',
            'show_user'   => true
        )));
        $this->assertEquals('Laminas\Twitter\SearchOptions', get_class($this->twitter->getOptions()));
        $this->assertEquals('fr', $this->twitter->getOptions()->getLanguage());
        $this->assertEquals('mixed', $this->twitter->getOptions()->getResultType());
    }

    public function testWithQueryInConfig()
    {
        $this->twitter->setResponseType('json');
        $this->twitter->setOptions(new Config\Config(array(
            'q'           => 'laminas',
            'lang'        => 'fr',
            'result_type' => 'mixed',
            'show_user'   => true
        )));
        $response = $this->twitter->execute();
        $this->assertEquals('laminas', $this->twitter->getOptions()->getQuery());
        $this->assertEquals('fr', $this->twitter->getOptions()->getLanguage());
        $this->assertEquals('mixed', $this->twitter->getOptions()->getResultType());
        $this->assertInternalType('array', $response);
        $this->assertTrue((isset($response['results'][0]) && $response['results'][0]['iso_language_code'] == "fr"));
    }

    public function testWithQueryAliasInConfig()
    {
        $this->twitter->setResponseType('json');
        $this->twitter->setOptions(new Config\Config(array(
            'query'       => 'laminas',
            'lang'        => 'fr',
            'result_type' => 'mixed',
            'show_user'   => true
        )));
        $response = $this->twitter->execute();
        $this->assertEquals('laminas', $this->twitter->getOptions()->getQuery());
        $this->assertEquals('fr', $this->twitter->getOptions()->getLanguage());
        $this->assertEquals('mixed', $this->twitter->getOptions()->getResultType());
        $this->assertInternalType('array', $response);
        $this->assertTrue((isset($response['results'][0]) && $response['results'][0]['iso_language_code'] == "fr"));
    }

    public function testWithNotQueryAndConfigOnExecute()
    {
        $this->twitter->setResponseType('json');
        $response = $this->twitter->execute(null, new Config\Config(array(
            'q'                => 'laminas',
            'lang'             => 'fr',
            'result_type'      => 'mixed',
            'show_user'        => true,
            'include_entities' => true
        )));
        $this->assertNotEquals('laminas', $this->twitter->getOptions()->getQuery());
        $this->assertNotEquals('fr', $this->twitter->getOptions()->getLanguage());
        $this->assertInternalType('array', $response);
        $this->assertTrue((isset($response['results'][0]) && $response['results'][0]['iso_language_code'] == "fr"));
        $this->assertTrue((isset($response['results'][0]) && isset($response['results'][0]['entities'])));
    }

    public function testWithNotQueryAndConfigOnExecuteWithLongName()
    {
        $this->twitter->setResponseType('json');
        $response = $this->twitter->execute(null, new Config\Config(array(
            'query'            => 'laminas',
            'language'         => 'fr',
            'results_per_page' => 10,
            'result_type'      => 'mixed',
            'show_user'        => true,
            'include_entities' => true
        )));
        $this->assertNotEquals('laminas', $this->twitter->getOptions()->getQuery());
        $this->assertNotEquals('fr', $this->twitter->getOptions()->getLanguage());
        $this->assertInternalType('array', $response);
        $this->assertTrue((isset($response['results'][0]) && $response['results'][0]['iso_language_code'] == "fr"));
        $this->assertTrue((isset($response['results'][0]) && isset($response['results'][0]['entities'])));
    }

    public function testWithQueryAndConfigOnExecute()
    {
        $this->twitter->setResponseType('json');
        $response = $this->twitter->execute('laminas', new Config\Config(array(
            'lang'        => 'fr',
            'result_type' => 'mixed',
            'show_user'   => true
        )));
        $this->assertNotEquals('laminas', $this->twitter->getOptions()->getQuery());
        $this->assertNotEquals('fr', $this->twitter->getOptions()->getLanguage());
        $this->assertInternalType('array', $response);
        $this->assertTrue((isset($response['results'][0]) && $response['results'][0]['iso_language_code'] == "fr"));
    }

    public function testSetOptionsWithSearchOptions()
    {
        $this->twitter->setOptions(new Twitter\SearchOptions(array(
            'lang'             => 'fr',
            'result_type'      => 'mixed',
            'show_user'        => true,
            'include_entities' => false
        )));
        $this->assertEquals('fr', $this->twitter->getOptions()->getLanguage());
        $this->assertEquals('mixed', $this->twitter->getOptions()->getResultType());
        $response = $this->twitter->execute('laminas');
        $this->assertTrue((isset($response['results'][0]) && !isset($response['results'][0]['entities'])));
    }

    public function testSetOptionsWithSearchOptionsByGetter()
    {
        $searchOptions = new Twitter\SearchOptions();
        $searchOptions->setLanguage('en');
        $searchOptions->setResultType('mixed');
        $searchOptions->setResultsPerPage(10);
        $searchOptions->setShowUser(true);
        $searchOptions->setIncludeEntities(false);
        $this->twitter->setOptions($searchOptions);
        $this->assertEquals('en', $this->twitter->getOptions()->getLanguage());
        $this->assertEquals('mixed', $this->twitter->getOptions()->getResultType());
        $response = $this->twitter->execute('laminas');
        $this->assertTrue((isset($response['results'][0]) && !isset($response['results'][0]['entities'])));
    }

    public function testSetOptionsWithNoEntities()
    {
        $this->twitter->setOptions(new Twitter\SearchOptions(array(
            'lang'             => 'en',
            'result_type'      => 'mixed',
            'show_user'        => true,
            'include_entities' => false
        )));
        $response = $this->twitter->execute('laminas');
        $this->assertNotEquals('laminas', $this->twitter->getOptions()->getQuery());
        $this->assertTrue((isset($response['results'][0]) && !isset($response['results'][0]['entities'])));
    }

    public function testJsonSearchContainsWordReturnsArray()
    {
        $this->twitter->setResponseType('json');
        $response = $this->twitter->execute('laminas');
        $this->assertInternalType('array', $response);
    }

    public function testAtomSearchContainsWordReturnsObject()
    {
        $this->twitter->setResponseType('atom');
        $response = $this->twitter->execute('laminas');

        $this->assertInstanceOf('Laminas\Feed\Reader\Feed\Atom', $response);
    }

    public function testJsonSearchRestrictsLanguageReturnsArray()
    {
        $this->twitter->setResponseType('json');
        $response = $this->twitter->execute('laminas', array('lang' => 'de'));
        $this->assertInternalType('array', $response);
        $this->assertTrue((isset($response['results'][0]) && $response['results'][0]['iso_language_code'] == "de"));
    }

    public function testJsonSearchWithArrayOptions()
    {
        $this->twitter->setResponseType('json');
        $response = $this->twitter->execute('laminas', array(
            'lang'        => 'fr',
            'result_type' => 'recent',
            'show_user'   => true
        ));
        $this->assertNotEquals('fr', $this->twitter->getOptions()->getLanguage());
        $this->assertNotEquals('recent', $this->twitter->getOptions()->getResultType());
        $this->assertInternalType('array', $response);
        $this->assertTrue((isset($response['results'][0]) && $response['results'][0]['iso_language_code'] == "fr"));
    }

    public function testAtomSearchRestrictsLanguageReturnsObject()
    {
        $this->markTestIncomplete('Problem with missing link method.');

        $this->twitter->setResponseType('atom');
        $response = $this->twitter->execute('laminas', array('lang' => 'de'));
        $this->assertInstanceOf('Laminas\Feed\Reader\Feed\Atom', $response);
        $this->assertTrue((strpos($response->link('self'), 'lang=de') !== false));
    }

    public function testJsonSearchReturnTwentyResultsReturnsArray()
    {
        $this->twitter->setResponseType('json');
        $response = $this->twitter->execute('php', array(
            'rpp'              => '20',
            'lang'             => 'en',
            'result_type'      => 'recent',
            'include_entities' => false
        ));
        $this->assertNotEquals(20, $this->twitter->getOptions()->getResultsPerPage());
        $this->assertInternalType('array', $response);
        $this->assertEquals(count($response['results']), 20);
    }

    public function testAtomSearchReturnTwentyResultsReturnsObject()
    {
        $this->twitter->setResponseType('atom');
        $response = $this->twitter->execute('php', array(
            'rpp'              => 20,
            'lang'             => 'en',
            'result_type'      => 'recent',
            'include_entities' => false
        ));
        $this->assertInstanceOf('Laminas\Feed\Reader\Feed\Atom', $response);
        $this->assertTrue(($response->count() == 20));
    }

    public function testAtomSearchShowUserReturnsObject()
    {
        $this->twitter->setResponseType('atom');
        $response = $this->twitter->execute('laminas', array('show_user' => 'true'));
        $this->assertInstanceOf('Laminas\Feed\Reader\Feed\Atom', $response);
    }
}
