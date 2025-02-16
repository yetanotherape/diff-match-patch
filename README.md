# Diff-Match-Patch 
[![Latest Stable Version](https://poser.pugx.org/yetanotherape/diff-match-patch/v)](https://packagist.org/packages/yetanotherape/diff-match-patch)
[![CI Status](https://github.com/yetanotherape/diff-match-patch/workflows/CI/badge.svg)](https://github.com/yetanotherape/diff-match-patch/actions)
[![Total Downloads](https://poser.pugx.org/yetanotherape/diff-match-patch/downloads)](https://packagist.org/packages/yetanotherape/diff-match-patch)

The Diff Match and Patch libraries offer robust algorithms to perform the operations required for synchronizing plain 
text:

* computes character-based diff of two texts
* performs fuzzy match of given string
* applies a list of patches.

This is the port of Google's [diff-match-patch](https://github.com/google/diff-match-patch) library to PHP.

## Diff

Compare two plain text and efficiently return a array of differences. It works with characters, but if you want 
to compute word-based or line-based diff — you can easily 
[tune](https://web.archive.org/web/20160110201643/https://code.google.com/p/google-diff-match-patch/wiki/LineOrWordDiffs) 
it for your needs.

Usage:
```php
<?php

use DiffMatchPatch\DiffMatchPatch;

$text1 = "The quick brown fox jumps over the lazy dog.";
$text2 = "That quick brown fox jumped over a lazy dog.";
$dmp = new DiffMatchPatch();
$diffs = $dmp->diff_main($text1, $text2, false);
var_dump($diffs);
```
Returns:
```php
array(
    array(DiffMatchPatch::DIFF_EQUAL, "Th"),
    array(DiffMatchPatch::DIFF_DELETE, "e"),
    array(DiffMatchPatch::DIFF_INSERT, "at"),
    array(DiffMatchPatch::DIFF_EQUAL, " quick brown fox jump"),
    array(DiffMatchPatch::DIFF_DELETE, "s"),
    array(DiffMatchPatch::DIFF_INSERT, "ed"),
    array(DiffMatchPatch::DIFF_EQUAL, " over "),
    array(DiffMatchPatch::DIFF_DELETE, "the"),
    array(DiffMatchPatch::DIFF_INSERT, "a"),
    array(DiffMatchPatch::DIFF_EQUAL, " lazy dog."),
)
```

[Demo](http://neil.fraser.name/software/diff_match_patch/svn/trunk/demos/demo_diff.html)

## Match

Given a search string, find its best fuzzy match in a plain text near the given location. Weighted for both accuracy 
and location.

Usage:
```php
<?php

use DiffMatchPatch\DiffMatchPatch;

$dmp = new DiffMatchPatch();
$text = "The quick brown fox jumps over the lazy fox.";
$pos = $dmp->match_main($text, "fox", 0); // Returns 16
$pos = $dmp->match_main($text, "fox", 40); // Returns 40
$pos = $dmp->match_main($text, "jmps"); // Returns 20
$pos = $dmp->match_main($text, "jmped"); // Returns -1
$pos = $dmp->Match_Threshold = 0.7;
$pos = $dmp->match_main($text, "jmped"); // Returns 20
```

[Demo](http://neil.fraser.name/software/diff_match_patch/svn/trunk/demos/demo_diff.html)

## Patch

Apply a list of patches in 
[Unidiff-like format](https://web.archive.org/web/20161002083301/https://code.google.com/p/google-diff-match-patch/wiki/Unidiff) 
onto plain text. Use best-effort to apply patch even when the underlying text doesn't match.

Usage:
```php
<?php

use DiffMatchPatch\DiffMatchPatch;

$dmp = new DiffMatchPatch();
$patches = $dmp->patch_make("The quick brown fox jumps over the lazy dog.", "That quick brown fox jumped over a lazy dog.");
// @@ -1,11 +1,12 @@
//  Th
// -e
// +at
//   quick b
// @@ -22,18 +22,17 @@
//  jump
// -s
// +ed
//   over
// -the
// +a
//   laz
$result = $dmp->patch_apply($patches, "The quick red rabbit jumps over the tired tiger.");
var_dump($result);
```
Returns:
```php
array(
    "That quick red rabbit jumped over a tired tiger.",
    array (
        true,
        true,
    ),
);
```

[Demo](http://neil.fraser.name/software/diff_match_patch/svn/trunk/demos/demo_patch.html)

## API

Currently this library available in:
 * PHP
 * [C++](https://github.com/google/diff-match-patch/wiki/Language:-Cpp)
 * [C#](https://github.com/google/diff-match-patch/wiki/Language:-C%23)
 * [Dart](https://github.com/google/diff-match-patch/wiki/Language:-Dart)
 * [Java](https://github.com/google/diff-match-patch/wiki/Language:-Java)
 * [JavaScript](https://github.com/google/diff-match-patch/wiki/Language:-JavaScript)
 * [Lua](https://github.com/google/diff-match-patch/wiki/Language:-Lua)
 * [Objective-C](https://github.com/google/diff-match-patch/wiki/Language:-Objective-C)
 * [Python](https://github.com/google/diff-match-patch/wiki/Language:-Python)

Regardless of language, each library uses the same 
[API](https://web.archive.org/web/20160922004754/https://code.google.com/p/google-diff-match-patch/wiki/API) 
and the same functionality.

## Algorithms

This library implements [Myer's diff algorithm](http://neil.fraser.name/software/diff_match_patch/myers.pdf) which is 
generally considered to be the best general-purpose diff. A layer of 
[pre-diff speedups and post-diff cleanups](http://neil.fraser.name/writing/diff/) surround the diff algorithm, improving 
both performance and output quality.

This library also implements a [Bitap matching algorithm](http://en.wikipedia.org/wiki/Bitap_algorithm) at the heart 
of a flexible [matching and patching strategy](http://neil.fraser.name/writing/patch/).

## Requirements

* PHP 7.3+
* [Composer](http://getcomposer.org/)

## Installation

```
composer require yetanotherape/diff-match-patch
```

## License

Diff-Match-Patch is licensed under the Apache License 2.0 - see the `LICENSE` file for details





