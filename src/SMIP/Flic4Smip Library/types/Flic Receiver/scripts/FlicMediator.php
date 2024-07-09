<?php

require_once 'load_cms.php';

require_once 'thinkiq_context.php';
$context = new Context();

if (!defined('JPATH_BASE')) define('JPATH_BASE', dirname(__DIR__));

require_once JPATH_BASE . '/components/com_thinkiq/config.php';
$config = new TiqConfig();
//echo "running on: " . $context->std_inputs->node_id;

// Create an instance of the Motor on which this script is currently operating (it's a Script on a Type so it can operate on many instances of that Type)
use \TiqUtilities\Model\Node;
$flicReceiver = Node::getInstance($context->std_inputs->node_id);
// var_dump($flicReceiver->GetMappings());

// use relative timespan
$start_time = (new DateTime())->sub(DateInterval::createFromDateString('0 week 0 days 0 hours 5 mins'))->format(DateTimeInterface::RFC3339_EXTENDED);
$end_time = (new DateTime())->sub(DateInterval::createFromDateString('0 week 0 days 0 hours -1 mins'))->format(DateTimeInterface::RFC3339_EXTENDED);

// use absolute timespan
// $start_time = (new DateTime("2024-01-22T08:00:00-05:00"))->format(DateTimeInterface::RFC3339_EXTENDED);
// $end_time = (new DateTime(  "2024-01-22T20:00:00-05:00"))->format(DateTimeInterface::RFC3339_EXTENDED);

$flicReceiver->updateData($start_time, $end_time);




$context->return_data();