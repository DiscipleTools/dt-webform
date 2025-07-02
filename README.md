![Build Status](https://github.com/DiscipleTools/dt-webform/actions/workflows/ci.yml/badge.svg?branch=master)

![Plugin Banner](https://raw.githubusercontent.com/DiscipleTools/dt-webform/master/documentation/banner.png)

# Disciple.Tools - DT Webform

A comprehensive WordPress plugin for Disciple.Tools that enables users to create embeddable web forms that collect data and create records directly in the Disciple.Tools system. The plugin provides an intuitive admin interface for form creation and management, lightweight responsive forms for embedding, and secure API endpoints for form submission.

## Purpose

The DT Webform plugin bridges the gap between external websites and Disciple.Tools by providing:

- **Seamless Data Collection**: Create forms that directly populate DT records (contacts, groups, etc.)
- **Easy Integration**: Generate embed codes for external websites or use direct links
- **Field Mapping**: Automatically integrate with existing DT fields or add custom fields
- **Secure Submission**: Built-in validation, sanitization, and CSRF protection
- **Mobile-First Design**: Responsive forms that work across all devices

This plugin is ideal for churches, ministries, and organizations using Disciple.Tools who want to collect information from their websites, social media, or other external sources without manual data entry.

## Key Features

#### Form Builder
- Visual form builder with drag-and-drop interface
- Integration with all Disciple.Tools field types
- Custom field support (text, textarea, dropdown, checkbox, etc.)
- Form preview and validation
- Field ordering and configuration

#### Responsive Forms
- Mobile-first, responsive design
- Fast loading with minimal CSS/JS footprint
- Accessibility compliant (WCAG 2.1 AA)
- Cross-browser compatibility
- Progressive enhancement

#### Embedding Options
- Generate iframe embed codes
- Direct shareable links
- Standalone form pages
- WordPress shortcode support (for DT sites)

#### Security & Validation
- Server-side and client-side validation
- CSRF token protection
- Input sanitization and XSS prevention
- Rate limiting protection
- Secure API endpoints

## Usage

### Creating a Form

1. Navigate to **Disciple.Tools > Webforms** in your WordPress admin
2. Click **"Add New Form"**
3. Configure your form:
   - Set form title and description
   - Choose target DT record type (contacts, groups, etc.)
   - Select DT fields to include
   - Add custom fields as needed
   - Configure validation rules
4. Preview your form
5. Activate the form when ready

### Embedding Forms

**Option 1: Direct Link**
- Copy the form URL: `https://yoursite.com/webform/{form_id}`
- Share directly or add as a link on your website

**Option 2: Iframe Embed**
- Copy the generated embed code from the form settings
- Paste into any website or content management system

**Option 3: WordPress Shortcode** (for DT sites)
```
[dt_webform id="123" title="true" description="true"]
```

### Form Submission Process

1. User fills out the form on your website
2. Data is validated and sanitized
3. A new DT record is created automatically
4. Custom fields are added as comments
5. User receives confirmation message
6. DT notifications are triggered (if configured)

## API Endpoints

The plugin provides secure REST API endpoints:

### Public Endpoints
- `POST /wp-json/dt-public/v1/webform/submit/{form_id}` - Submit form data
- `GET /wp-json/dt-public/v1/webform/{form_id}/config` - Get form configuration

### Admin Endpoints (require `manage_dt` capability)
- `GET /wp-json/dt-webform/v1/forms` - List all forms
- `POST /wp-json/dt-webform/v1/forms` - Create new form
- `PUT /wp-json/dt-webform/v1/forms/{form_id}` - Update form
- `DELETE /wp-json/dt-webform/v1/forms/{form_id}` - Delete form

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Disciple.Tools Theme installed and active
- Administrator or user with `manage_dt` capability

## Installing

1. Download the plugin from the [releases page](https://github.com/DiscipleTools/dt-webform/releases)
2. Upload and install as a standard WordPress plugin via Admin > Plugins > Add New
3. Activate the plugin
4. Navigate to Disciple.Tools > Webforms to get started

Alternatively, install directly from the Disciple.Tools Community Site.

## Technical Details

### Supported Field Types

**DT Field Integration:**
- Text fields
- Textarea fields
- Select dropdowns
- Multi-select fields
- Date fields
- Number fields
- Boolean/toggle fields
- Communication channels
- Location fields

**Custom Fields:**
- Text input
- Textarea
- Dropdown select
- Checkbox/toggle
- Radio buttons
- Multi-select checkboxes

### Security Features

- CSRF token validation on all submissions
- Input sanitization and validation
- XSS prevention
- SQL injection prevention
- Rate limiting (configurable)
- Secure API authentication

### Performance

- Lightweight assets (< 100KB combined CSS/JS)
- Efficient WordPress queries
- Caching support
- Mobile-optimized loading
- Progressive enhancement

## Troubleshooting

### Common Issues

**Forms not submitting:**
- Check that the form is activated
- Verify REST API endpoints are accessible
- Ensure proper permissions are set

**Styling issues:**
- Check for CSS conflicts with your theme
- Verify responsive design on different devices
- Test with different browsers

**Missing form data:**
- Verify field mappings are correct
- Check DT record permissions
- Review form validation settings

### Getting Help

- Check the [documentation](https://github.com/DiscipleTools/dt-webform/wiki)
- Search [existing issues](https://github.com/DiscipleTools/dt-webform/issues)
- Ask questions in [discussions](https://github.com/DiscipleTools/dt-webform/discussions)
- Contact support through the Disciple.Tools community

## Contribution

Contributions welcome! You can help by:

- Reporting issues and bugs in the [Issues](https://github.com/DiscipleTools/dt-webform/issues) section
- Suggesting features in the [Discussions](https://github.com/DiscipleTools/dt-webform/discussions) section  
- Contributing code via [Pull Requests](https://github.com/DiscipleTools/dt-webform/pulls)
- Improving documentation and translations

For detailed contribution guidelines, see [CONTRIBUTING.md](https://github.com/DiscipleTools/dt-webform/blob/master/CONTRIBUTING.md).

## License

This plugin is licensed under the GPL v2 or later.

## Screenshots

![Form Builder Interface](documentation/screenshots/form-builder.png)
*Intuitive form builder with drag-and-drop functionality*

![Responsive Form Display](documentation/screenshots/responsive-form.png)
*Mobile-first responsive form design*

![Form Management Dashboard](documentation/screenshots/form-management.png)
*Comprehensive form management and analytics*
