<?php

namespace App;

use DOMDocument;
use DOMXPath;

function fetchHTML(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36");

    $html = curl_exec($ch);

    if (curl_errno($ch)) {
        // Log cURL error
        file_put_contents(__DIR__ . '/log/curl_error.log', curl_error($ch) . "\n", FILE_APPEND);
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    return $html;
}

function randomSixDigit()
{
    return substr(str_shuffle("1234567890"), 0, 6);
}

function GetNotFound(string $html)
{
    //Parse the HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($html);

    $xpath = new DOMXPath($dom);

    // Helper to fetch text content or return empty string
    $getNodeContent = function ($query) use ($xpath) {
        $node = $xpath->query($query)->item(0);
        return $node ? trim($node->textContent) : '';
    };

    //check whether there's container error
    return $getNodeContent("//div[@class='container error']");
}

function hasRequestedTag(string $tags, string $html)
{
    // Parse the HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $tag_name = $tags;

    $xpath = new DOMXPath($dom);

    // Function to get the content of nodes (using direct XPath query)
    $validatedTag = function () use ($xpath, $tag_name) {
        // Direct XPath query
        $query = "//a[contains(@href, '/tag/')]/span[@class='name']";

        // Execute the XPath query
        $nodes = $xpath->query($query);
        $isValid = false; // Variable to track if the tag is valid

        // Loop through the nodes to check if the content matches the tag_name
        foreach ($nodes as $node) {
            // Check if the node's text content matches the tag
            if (trim($node->textContent) === $tag_name) {
                $isValid = true; // Set isValid to true if tag is found
            }
        }

        return $isValid; // Return true if the tag was found, false otherwise
    };

    return $validatedTag(); // Call and return the result of the function
}
function hasRequestedLang(string $lang, string $html)
{
    // Parse the HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $lang_name = $lang;

    $xpath = new DOMXPath($dom);

    // Function to get the content of nodes (using direct XPath query)
    $validatedLang = function () use ($xpath, $lang_name) {
        // Direct XPath query
        $query = "//a[contains(@href, '/language/')]/span[@class='name']";

        // Execute the XPath query
        $nodes = $xpath->query($query);
        $isValid = false; // Variable to track if the lang is valid

        // Loop through the nodes to check if the content matches the lang_name
        foreach ($nodes as $node) {
            // Check if the node's text content matches the lang
            if (trim($node->textContent) === $lang_name) {
                $isValid = true; // Set isValid to true if lang is found
            }
        }

        return $isValid; // Return true if the lang was found, false otherwise
    };

    return $validatedLang(); // Call and return the result of the function
}

function isValidTag($tag)
{
    $tagFirstLetter = strtoupper(substr($tag, 0, 1));

    $pagesByLetter = [
        'A' => [1, 4],
        'B' => [2, 6],
        'C' => [4, 7],
        'D' => [5, 8],
        'E' => [6, 9],
        'F' => [7, 10],
        'G' => [8, 11],
        'H' => [9, 13],
        'I' => [11, 13],
        'J' => [11, 14],
        'K' => [12, 16],
        'L' => [15, 17],
        'M' => [15, 20],
        'N' => [18, 22],
        '0' => [20, 23],
        'P' => [21, 24],
        'Q' => [22, 24],
        'R' => [22, 25],
        'S' => [23, 29],
        'T' => [27, 31],
        'U' => [29, 32],
        'V' => [30, 32],
        'W' => [30, 32],
        'X' => [31, 33],
        'Y' => [31, 34],
        'Z' => [32, 34]
    ];


    // Check if the first letter exists in the array
    if (array_key_exists($tagFirstLetter, $pagesByLetter)) {
        // Get the page range for the first letter
        $pageRange = $pagesByLetter[$tagFirstLetter];

        $suggestions = [];
        $bestMatch = null;
        $shortestDistance = PHP_INT_MAX;

        // Iterate through the specified page range
        for ($currentPage = $pageRange[0]; $currentPage <= $pageRange[1]; $currentPage++) {
            // Build the URL for the current page
            $url = "https://nhentai.net/tags/?page=$currentPage";

            // Fetch the HTML content of the page
            $html = file_get_contents($url);

            if ($html) {
                // Load the HTML into DOMDocument
                $dom = new DOMDocument();
                @$dom->loadHTML($html);

                // Use DOMXPath to extract the href attributes of <a> tags with the "/tag/" prefix
                $xpath = new DOMXPath($dom);
                $tags = $xpath->query("//a[contains(@href, '/tag/') and starts-with(@href, '/tag/')]/@href");

                // Loop through the tags to find the desired tag or similar ones
                foreach ($tags as $tagNode) {
                    $href = $tagNode->nodeValue; // e.g., "/tag/dark-skin/"
                    $tagValue = trim(str_replace(['/tag/', '/'], '', $href)); // Extract the tag value

                    // Check for an exact match
                    if ($tagValue === $tag) {
                        return true; // Tag found
                    }

                    // Compute similarity using Levenshtein distance
                    $distance = levenshtein($tag, $tagValue);

                    // Save suggestions if distance is within a reasonable threshold
                    if ($distance <= 3) { // Threshold for similarity (adjust as needed)
                        $suggestions[] = $tagValue;
                        if ($distance < $shortestDistance) {
                            $shortestDistance = $distance;
                            $bestMatch = $tagValue;
                        }
                    }
                }
            }
        }

        // Handle suggestions if no exact match is found
        if (!empty($suggestions)) {
            return "The tag you inputted is not found. Perhaps you mean '$bestMatch'?";
        }

        // No matches or suggestions found
        return "The tag you provided is not available on the nhentai website.";
    } else {
        // No page range for the given first letter
        return "The tag you provided is not available on the nhentai website.";
    }
}

function isValidLang($lang)
{
    $availableLang = ['japanese', 'english', 'translated', 'chinese'];

    $suggestions = [];
    $bestMatch = null;
    $shortestDistance = PHP_INT_MAX;

    foreach ($availableLang as $validLang) {

        if ($lang === $validLang) {
            return true;
        }

        $distance = levenshtein($lang, $validLang);

        // Save suggestions if distance is within a reasonable threshold
        if ($distance <= 3) { // Threshold for similarity (adjust as needed)
            $suggestions[] = $validLang;
            if ($distance < $shortestDistance) {
                $shortestDistance = $distance;
                $bestMatch = $validLang;
            }
        }
    }

    if ($suggestions) {
        return "The languague you provided is not found. perhaps you mean '$bestMatch'?";
    } else {
        return "The language you provided is not available on the nhentai website";
    }
}
