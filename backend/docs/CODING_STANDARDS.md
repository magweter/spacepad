# Coding Standards

This document outlines coding standards and best practices for the Spacepad backend codebase.

## Import Statements

**Always use import statements at the top of files instead of inline fully qualified class names.**

### ✅ Correct

```php
<?php

namespace App\Http\Controllers;

use App\Models\Display;
use App\Models\User;
use App\Services\InstanceService;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $displays = Display::where('status', 'active')->get();
        $user = User::find(1);
        $isValid = InstanceService::hasValidLicense();
    }
}
```

### ❌ Incorrect

```php
<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $displays = \App\Models\Display::where('status', 'active')->get();
        $user = \App\Models\User::find(1);
        $isValid = \App\Services\InstanceService::hasValidLicense();
    }
}
```

### Why?

- **Readability**: Import statements make it clear which classes are used in a file
- **Maintainability**: Easier to refactor and understand dependencies
- **IDE Support**: Better autocomplete and navigation
- **PSR Standards**: Follows PSR-12 coding standard
- **Consistency**: Matches Laravel conventions

### When to Use Fully Qualified Names

Only use fully qualified class names (`\App\Models\...`) when:
- There's a naming conflict that requires disambiguation
- You're using a class from a different namespace that's not commonly imported

In all other cases, use `use` statements at the top of the file.

