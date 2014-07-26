# Templates
**RDev** has a template system, which is meant to simplify adding dynamic content to web pages.  Simply create an HTML file with tags and then specify their values:
#### Template
```
Hello, {{username}}
```
#### Application Code
```php
use RDev\Views\Pages;

$template = new Pages\Template(PATH_TO_HTML_TEMPLATE);
$template->setTag("username", "Beautiful Man");
echo $template->getOutput(); // "Hello, Beautiful Man"
```

## Cross-Site Scripting
To sanitize data to prevent cross-site scripting (XSS), simply use the triple-brace syntax:
#### Template
```
{{{namesOfCouple}}}
```
#### Application Code
```php
use RDev\Views\Pages;

$template = new Pages\Template(PATH_TO_HTML_TEMPLATE);
$template->setTag("namesOfCouple", "Dave & Lindsey");
echo $template->getOutput(); // "Dave &amp; Lindsey"
```

Alternatively, you can output a string literal inside the triple-braces:
#### Template
```
{{{"Dave & Lindsey"}}}
```

This will output "Dave &amp; Lindsey".  

## Using PHP in Your Template
Keeping your view separate from your business logic is important.  However, there are times where it would be nice to be able to execute some PHP code to do things like for() loops to output a list.  With RDev's template system, you can do this:
#### Template
```
<ul><?php
foreach(["foo", "bar"] as $item)
{
    echo "<li>$item</li>";
}
?></ul>
```
#### Application Code
```php
use RDev\Views\Pages;

$template = new Pages\Template(PATH_TO_HTML_TEMPLATE);
echo $template->getOutput(); // "<ul><li>foo</li><li>bar</li></ul>"
```

You can also inject values into variables your template from your application code:
#### Template
```
<?php 
if($isAdministrator)
{
    echo "<a href="admin.php">Admin</a>"; 
}
?>
```
#### Application Code
```php
use RDev\Views\Pages;

$template = new Pages\Template(PATH_TO_HTML_TEMPLATE);
$template->setVar("isAdministrator", $user->isAdministrator());
echo $template->getOutput(); // "<a href=\"admin.php\">Admin</a>"
```

*Note*: PHP code is compiled first, followed by tags.  Therefore, it's possible to use the output of PHP code inside tags in your template.

## Escaping Tags
Want to escape a tag?  Easy!  Just add a backslash before the opening tag like so:
#### Template
```
Hello, {{username}}.  \{{I am escaped}}!
```
#### Application Code
```php
use RDev\Views\Pages;

$template = new Pages\Template(PATH_TO_HTML_TEMPLATE);
$template->setTag("username", "Mr Schwarzenegger");
echo $template->getOutput(); // "Hello, Mr Schwarzenegger.  {{I am escaped}}!"
```

## Custom Tag Placeholders
Want to use a custom character/string for the tag placeholders?  Easy!  Just specify it in the *Template* object like so:
#### Template
```
Hello, ^^username$$
```
#### Application Code
```php
use RDev\Views\Pages;

$template = new Pages\Template(PATH_TO_HTML_TEMPLATE);
$template->setOpenTagPlaceholder("^^");
$template->setCloseTagPlaceholder("$$");
$template->setTag("username", "Daft Punk");
echo $template->getOutput(); // "Hello, Daft Punk"
```