# TYPO3 Extension `redirect_generator`

This extensions creates proper redirect entries from a CSV file. 

Each target entry will be matched by the routing configuration. If the target is `http://demo.vm/company/contact`, the redirect will be created to the related page id! 

![Add Redirect](Resources/Public/Screenshots/redirect-add.png)

## Installation

**Requirements**

- TYPO3 9 / 10
- EXT:redirects

**Setup**

Install as any other extension:

- *Composer*: `composer require georgringer/redirect-generator`


## Usage

### Add single redirect

Use the following CLI command:
```bash
./bin/typo3 redirect:add /any-url https://domain.tld/your-final-url
```

The following options are available:

* `--status-code`: Define the status code, allowed are *301*,*302*, *303* and *307*.
* `--dry-run`: If set, the redirect won't be added

### Import CSV

Use the following CLI command:
```bash
./bin/typo3 redirect:import <path-to-file.csv>
```

A sample CSV file can be found at `EXT:redirect_generator/Resources/Private/Examples/ImportBasic.csv`

The following options are available:

* `--dry-run`: If set, the redirect won't be added
* `--external-domains`: Provide a comma separated list of domains which are external
