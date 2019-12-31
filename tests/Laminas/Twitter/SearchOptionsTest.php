<?php

/**
 * @see       https://github.com/laminas/laminas-twitter for the canonical source repository
 * @copyright https://github.com/laminas/laminas-twitter/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-twitter/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Twitter;

use Laminas\Twitter;

/**
 * @category   Laminas
 * @package    Laminas_Service_Twitter
 * @subpackage UnitTests
 * @group      Laminas_Service
 * @group      Laminas_Service_Twitter
 */
class SearchOptionsTest extends \PHPUnit_Framework_TestCase
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
        $this->twitter = new Twitter\Search();
    }

    public function testImmutableOption()
    {
        $expectedLang = 'fr';
        $this->twitter->setOptions(new Twitter\SearchOptions(array(
            'language' => $expectedLang,
        )));
        $options = $this->twitter->getOptions();
        $options->setLanguage('en');
        $actualOptions = $this->twitter->getOptions();

        $this->assertEquals($expectedLang, $actualOptions->getLanguage());
    }
}
