# TYPO3 Extension `redirect_generator`

This extensions creates proper redirects from a given URL. 

![Add Redirect](Resources/Public/Screenshots/redirect-add.png)

**Advantages over `.htaccess` entries**

- If the target URL changes (e.g. because page slug changes), the redirect will still work
- Redirects are managable via UI for site administrators
- No deployment needed

**Disadvantages**

- A bit slower + higher load

## Usage

**Requirements**

- TYPO3 9 / 10
- EXT:redirects

### Installation
Install as any other extension

### Setup

Use the following CLI command:
```bash
./bin/typo3 redirect:add /any-url https://domain.tld/your-final-url
```

The following options are available:

* `--status-code`: Define the status code, allowed are *301*,*302*, *303* and *307*.
* `--dry-run`: If set, the redirect won't be added
