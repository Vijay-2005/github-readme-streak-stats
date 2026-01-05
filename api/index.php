<?php

declare(strict_types=1);

// Load environment variables into $_SERVER for Vercel compatibility
// Vercel sets env vars via getenv(), but the app expects $_SERVER
if (!isset($_SERVER["TOKEN"]) && getenv("TOKEN")) {
    $_SERVER["TOKEN"] = getenv("TOKEN");
}
// Also check for additional tokens (TOKEN2, TOKEN3, etc.)
$index = 2;
while (getenv("TOKEN{$index}")) {
    $_SERVER["TOKEN{$index}"] = getenv("TOKEN{$index}");
    $index++;
}

// load functions
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../src/stats.php";
require_once __DIR__ . "/../src/card.php";

// load .env (for local development)
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1));
$dotenv->safeLoad();

// if environment variables are not loaded, display error
if (!isset($_SERVER["TOKEN"])) {
    $message = "Missing token in config. Check Contributing.md for details.";
    renderOutput($message, 500);
    exit();
}

// set cache to refresh once per three hours
$cacheMinutes = 3 * 60 * 60;
header("Expires: " . gmdate("D, d M Y H:i:s", time() + $cacheMinutes) . " GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: public, max-age=$cacheMinutes");

// redirect to demo site if user is not given
if (!isset($_REQUEST["user"])) {
    header("Content-Type: text/html");
    echo "<h1>GitHub Readme Streak Stats</h1>";
    echo "<p>Please provide a GitHub username using the 'user' query parameter.</p>";
    echo "<p>Example: <code>?user=DenverCoder1</code></p>";
    exit();
}

try {
    // get streak stats for user given in query string
    $user = preg_replace("/[^a-zA-Z0-9\-]/", "", $_REQUEST["user"]);
    $startingYear = isset($_REQUEST["starting_year"]) ? intval($_REQUEST["starting_year"]) : null;
    $contributionGraphs = getContributionGraphs($user, $startingYear);
    $contributions = getContributionDates($contributionGraphs);
    if (isset($_GET["mode"]) && $_GET["mode"] === "weekly") {
        $stats = getWeeklyContributionStats($contributions);
    } else {
        // split and normalize excluded days
        $excludeDays = normalizeDays(explode(",", $_GET["exclude_days"] ?? ""));
        $stats = getContributionStats($contributions, $excludeDays);
    }
    renderOutput($stats);
} catch (InvalidArgumentException | AssertionError $error) {
    error_log("Error {$error->getCode()}: {$error->getMessage()}");
    if ($error->getCode() >= 500) {
        renderOutput("An error occurred while fetching the contribution data.", $error->getCode());
    } else {
        renderOutput($error->getMessage(), $error->getCode());
    }
} catch (Exception $error) {
    error_log("Error: {$error->getMessage()}");
    renderOutput("An unexpected error occurred.", 500);
}
