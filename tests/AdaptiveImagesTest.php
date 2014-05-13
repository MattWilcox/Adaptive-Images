<?php

namespace AdaptiveImages;

class AdaptiveImagesTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $_SERVER['REQUEST_URI'] = "test.jpg";
    }

    public function testAutoLoad()
    {
        include_once dirname(__DIR__) . '/vendor/autoload.php';

        $this->assertTrue(class_exists('AdaptiveImages\\AdaptiveImages'));
    }

    public function testConfiguration()
    {
        $processor = new AdaptiveImages();

        $this->assertInternalType("array", $processor->getResolutions());
        $this->assertInternalType('string', $processor->getCachePath());
        $this->assertInternalType('integer', $processor->getJpgQuality());
        $this->assertInternalType('boolean', $processor->getSharpen());
        $this->assertInternalType('boolean', $processor->getWatchCache());
        $this->assertInternalType('integer', $processor->getBrowserCache());
        $this->assertInternalType('string', $processor->getDocumentRoot());
        $this->assertInternalType('string', $processor->getRequestedUri());
        $this->assertInternalType('string', $processor->getRequestedFile());
        $this->assertInternalType('string', $processor->getSourceFile());
        $this->assertInternalType('boolean', $processor->getResolution());
    }
}
