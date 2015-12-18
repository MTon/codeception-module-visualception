<?php

namespace Codeception\Module;

use Codeception\Module\VisualCeption\ImageNotFoundException;
use Codeception\Module\VisualCeption\ImageDeviationException;

use Codeception\Module\VisualCeption\Storage\Factory;
use Codeception\Module\VisualCeption\Image\Comparison;
use Codeception\Module\VisualCeption\Html\Screenshot;
use Codeception\Module\VisualCeption\Html\Manipulation;
use Codeception\TestCase;
use Imagick;

/**
 * Class VisualCeption
 *
 * @copyright Copyright (c) 2014 G+J Digital Products GmbH
 * @license MIT license, http://www.opensource.org/licenses/mit-license.php
 * @package Codeception\Module
 *
 * @author Nils Langner <langner.nils@guj.de>
 * @author Torsten Franz
 * @author Sebastian Neubert
 */
class VisualCeption extends \Codeception\Module
{
    private $maximumDeviation = 0;

    /**
     * @var \RemoteWebDriver
     */
    private $webDriver = null;

    /**
     * @var \Storage
     */
    private $storageStrategy;

    /**
     * Create an object from VisualCeption Class
     *
     * @param array $config
     * @return result
     */
    public function __construct($config)
    {
        $result = parent::__construct($config);
        $this->init();
        return $result;
    }

    /**
     * Initialize the module and read the config.
     *
     * @throws \RuntimeException
     */
    private function init()
    {
        if (array_key_exists('maximumDeviation', $this->config)) {
            $this->maximumDeviation = $this->config["maximumDeviation"];
        }

        $this->storageStrategy = Factory::getStorage($this->config);
    }

    /**
     * Event hook before a test starts
     *
     * @param \Codeception\TestCase $test
     * @throws \Exception
     */
    public function _before(TestCase $test)
    {
        if (!$this->hasModule("WebDriver")) {
            throw new \Exception("VisualCeption uses the WebDriver. Please be sure that this module is activated.");
        }
        $this->webDriver = $this->getModule("WebDriver")->webDriver;
    }

    /**
     * Compare the reference image with a current screenshot, identified by their indentifier name
     * and their element ID.
     *
     * @param string $identifier Identifies your test object
     * @param string $elementID DOM ID of the element, which should be screenshotted
     * @param string|array $excludeElements Element name or array of Element names, which should not appear in the screenshot
     */
    public function seeVisualChanges($identifier, $elementId = null, $excludedElements = array())
    {
        $comparisonResult = $this->getVisualChanges($identifier, $elementId, (array)$excludedElements);

        $this->assertTrue(true);
        if ($comparisonResult->getDeviation() <= $this->maximumDeviation ) {
            throw new ImageDeviationException("The deviation of the taken screenshot is too low (" . $comparisonResult->getDeviation() . "%)",
                $comparisonResult, $this->storageStrategy, $identifier);
        }
    }

    /**
     * Compare the reference image with a current screenshot, identified by their indentifier name
     * and their element ID.
     *
     * @param string $identifier identifies your test object
     * @param string $elementID DOM ID of the element, which should be screenshotted
     * @param string|array $excludeElements string of Element name or array of Element names, which should not appear in the screenshot
     */
    public function dontSeeVisualChanges($identifier, $elementId = null, $excludedElements = array())
    {
        $comparisonResult = $this->getVisualChanges($identifier, $elementId, (array)$excludedElements);

        $this->assertTrue(true);
        if($comparisonResult->getDeviation() > $this->maximumDeviation ) {
            throw new ImageDeviationException("The deviation of the taken screenshot is too high (" . $comparisonResult->getDeviation() . "%)",
                $comparisonResult, $this->storageStrategy, $identifier);
        }
    }

    private function getVisualChanges($identifier, $elementId, array $excludedElements)
    {
        $currentImage = $this->getCurrentImage($excludedElements, $elementId);

        $expectedImage = null;
        try {
            $expectedImage = $this->storageStrategy->getImage($identifier);
        } catch (ImageNotFoundException $e) {
            $this->storageStrategy->setImage($currentImage, $identifier);
            $expectedImage = $currentImage;
        }
        return $this->getComparisonResult($expectedImage, $currentImage);
    }

    private function getComparisonResult(Imagick $expectedImage, Imagick $currentImage)
    {
        try {
            $imageCompare = new Comparison();
            return $imageCompare->compare($expectedImage, $currentImage);
        } catch (\ImagickException $e) {
            $this->debug("IMagickException! Could not compare images.\nExceptionMessage: " . $e->getMessage());
            $this->fail($e->getMessage());
        }
    }

    private function getCurrentImage(array $excludedElements, $elementId)
    {
        $htmlManipulator = new Manipulation($this->webDriver);
        $htmlManipulator->hideElements($excludedElements);

        $htmlScreenshot = new Screenshot($this->webDriver);
        return $htmlScreenshot->takeScreenshot($elementId);
    }
}
