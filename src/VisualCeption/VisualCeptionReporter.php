<?php
/**
 *
 */

namespace Codeception\Module;

use Codeception\Module\VisualCeption\ImageDeviationException;
use Codeception\TestCase;

class VisualCeptionReporter extends \Codeception\Module
{
    private $reporter;

    public function __construct($config)
    {
        $result = parent::__construct($config);
        $this->init();
        return $result;
    }

    private function init()
    {
        $this->debug("Initializing VisualCeptionReport");
        $this->setReporter($this->config["reporter"]);
    }

    private function setReporter($config)
    {
        $reporterClass = $config['class'];
        $this->reporter = new $reporterClass($config);
    }

    public function _beforeSuite()
    {
        if (!$this->hasModule("VisualCeption")) {
            throw new \Exception("VisualCeptionReporter uses VisualCeption. Please be sure that this module is activated.");
        }
    }

    public function _afterSuite()
    {
        $this->reporter->finish();
    }

    public function _failed(TestCase $test, $fail)
    {
        if ($fail instanceof ImageDeviationException) {
            $this->reporter->processFailure($fail);
        }
    }
}
