# No dev checker for GrumPHP
This simple package will check your composer.json file for dev dependencies and will fail if there are any that are not in the allowlist.

## Installation
Require this package with composer using the following command:

```bash
composer require janvernieuwe/dev-branch-check --dev
```

Then add this to your grumphp file.

```yaml
parameters:
    tasks:
        janvernieuwe_dev_branch:
            allowed_packages:
                - roave/security-advisories # This is an example
            fail_on_commit: false # Default true, allows for committingm wil still fail on CI
    extensions:
        - Janvernieuwe\DevBranchCheck\ExtensionLoader
```

## Licence (MIT)

Copyright 2023 Jan Vernieuwe

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.