<?php

$inline = "<p>Hello</p>";
$attributes = '<a href="/x">link</a>';
$selfClosing = "<br/>";
$block = 'before <div>content</div> after';
$closingOnly = "</section>";

// Not only markup. Every embedded language earns a HereDoc at any length,
// because the delimiter is what names it for an editor.
$query = "SELECT id, name FROM users WHERE active = 1";
$json = '{"name": "ada", "age": 36}';
$ini = "[database]\nhost = localhost\nport = 5432";
$markdown = "## Heading\n- first item";
$xml = '<?xml version="1.0"?><root/>';
