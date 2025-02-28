# PHPack

PHPack is a powerful tool designed to help you modularize and compile your PHP and HTML components into a single, optimized PHP file. This enhances the organization and maintainability of your web projects.

## Features
- **Modular Design**: Break down your web pages into reusable components for better code management.
- **JSON Configuration**: Define your page structure using intuitive JSON files.
- **Automatic Compilation**: Seamlessly compile your components into a single PHP file for easy deployment.

## Installation

### Via Composer (Recommended)
To install PHPack using Composer, simply run:

```bash
composer require rpurinton/phpack
```

This will make the `phpack` command available globally, allowing you to use it in any project.

### Manual Installation
If you prefer to install PHPack manually, you can clone the repository and set up the executable:

```bash
git clone https://github.com/rpurinton/phpack.git
cd phpack
sudo chmod +x phpack && sudo cp phpack /usr/bin/
```

## Usage
1. **Create Your Components**: Organize your PHP and HTML components in the `parts` directory.
2. **Define Page Structure**: Use JSON files in the `pages` directory to define the structure of your web pages.
3. **Run PHPack**: Execute the `phpack` script to compile your pages.

```bash
phpack
```

## Example

### Directory Structure
Here's an example of how you can structure your project:

```
yourproject/
├── pages/
│   ├── home.json
│   ├── about.json
│   ├── contact.json
│   └── blog.json
└── parts/
    ├── head/
    │   ├── head.json
    │   ├── meta.html
    │   ├── styles.html
    │   └── scripts.html
    ├── body/
    │   ├── header.html
    │   ├── footer.html
    │   ├── content/
    │   │   ├── intro.html
    │   │   ├── features.html
    │   │   └── testimonials.html
    │   ├── about.html
    │   ├── contact.html
    │   └── blog/
    │       ├── post1.html
    │       ├── post2.html
    │       └── post3.html
```

### JSON Example
**`home.json`**
```json
{
    "parts": [
        "<html lang=\"en\">",
        "head/head.json",
        "<body>",
        "body/header.html",
        "body/content.json",
        "body/footer.html",
        "</body></html>"
    ]
}
```

The above example would result in the creation of:

```
yourproject/
└── public/
    ├── home.php
    ├── about.php
    ├── contact.php
    └── blog.php
```

You can use the `public` folder as your web root for your web server. A part can be either `.html`, `.php`, text/html, or another `.json` file that includes more parts.

## License
This project is licensed under the MIT License.

## Contributing
We welcome contributions from the community! Feel free to submit issues or pull requests.

## Contact
For more information, please contact [Russell Purinton](mailto:russell.purinton@gmail.com).
