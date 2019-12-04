# Symfony Expression Language Arrow Function

[![Build Status](https://travis-ci.org/uuf6429/expression-language-arrowfunc.svg?branch=master)](https://travis-ci.org/uuf6429/expression-language-arrowfunc)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.1-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://raw.githubusercontent.com/uuf6429/expression-language-arrowfunc/master/LICENSE)
[![Coverage](https://codecov.io/gh/uuf6429/expression-language-arrowfunc/branch/master/graph/badge.svg?token=Bu2nK2Kq77)](https://codecov.io/github/uuf6429/expression-language-arrowfunc?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/uuf6429/expression-language-arrowfunc/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/uuf6429/expression-language-arrowfunc/?branch=master)
[![Packagist](https://img.shields.io/packagist/v/uuf6429/expression-language-arrowfunc.svg)](https://packagist.org/packages/uuf6429/expression-language-arrowfunc)

Arrow function (aka "Lambda Expression" or "Anonymous Function") support in Symfony [Expression Language component](https://symfony.com/doc/current/components/expression_language.html).

## Syntax
```
 (a) -> { a * 2 }
  ^  ^      ^
  |  |      '----- Function body is a single expression that can make use of passed parameters or global variables.
  |  '------------ The lambda operator - input parameters are to the left and the output expression to the right.
  '--------------- Comma-separated list of parameters passed to arrow function.
```

## Safety

Returning callbacks can be dangerous in PHP. If the returned value is not checked, PHP may end up executing arbitrary global functions, static class methods or object methods.

### Problem Example
```php
$language = new ExpressionLanguage();
$expression = '(value) -> { value > 20 }';
$filter = $language->evaluate($expression);
$values = array_filter([18, 23, 40], $filter);
```

If `$expression` returns a string or array, `array_filter()` will arbitrarily call whatever was returned.

### Solution

There are two solutions:
- Set the type declaration of methods using the callback to `Closure` (*not `Callable`!*) - prone to mistakes and quite risky.
- The engine returns the callback wrapped in an object that cannot be invoked by default - this is the safest option (and default one).
