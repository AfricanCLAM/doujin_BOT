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

function random()
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

function Validatetag(string $tags, string $html)
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
