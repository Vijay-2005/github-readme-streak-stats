<?php

declare(strict_types=1);

// load functions
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../src/stats.php";
require_once __DIR__ . "/../src/card.php";

// load .env if it exists (for local development)
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1));
$dotenv->safeLoad();

// Check for TOKEN in environment variables (Vercel uses getenv() or $_ENV)
$token = getenv("TOKEN") ?: ($_ENV["TOKEN"] ?? ($_SERVER["TOKEN"] ?? null));

// if environment variables are not loaded, display error
if (!$token) {
    $message = "Missing token in config. Check Contributing.md for details.";
    renderOutput($message, 500);
}

// Make TOKEN available for other parts of the code
$_SERVER["TOKEN"] = $token;

// set cache to refresh once per day
$timestamp = time();
$today = date("Y-m-d");
$tomorrow = date("Y-m-d", strtotime("tomorrow"));
$seconds = strtotime($tomorrow) - $timestamp;
header("Cache-Control: max-age={$seconds}, s-maxage={$seconds}, stale-while-revalidate");
header("Date: " . date("D, d M Y H:i:s", $timestamp) . " GMT");
header("Expires: " . date("D, d M Y H:i:s", strtotime($tomorrow)) . " GMT");

// set content type to SVG image
header("Content-Type: image/svg+xml");

// get streak stats for user
try {
    $contributionGraphs = getContributionGraphs();
    $contributions = getContributionDates($contributionGraphs);
    $stats = getContributionStats($contributions);
    echo generateCard($stats);
} catch (InvalidArgumentException $error) {
    renderOutput($error->getMessage(), 400);
} catch (AssertionError | Exception $error) {
    renderOutput($error->getMessage(), 500);
}
