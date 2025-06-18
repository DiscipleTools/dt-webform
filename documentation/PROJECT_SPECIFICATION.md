# DT Webform Plugin - Project Specification

## Overview

The DT Webform plugin is a WordPress plugin for Disciple.Tools that enables users to create embeddable web forms that collect data and create records directly in the Disciple.Tools system. The plugin provides an admin interface for form creation and management, lightweight responsive forms for embedding, and secure API endpoints for form submission.

## Core Features

### 1. Admin Interface for Form Management

The admin interface allows users to:
- Create, edit, and delete web forms
- Configure forms for any Disciple.Tools record type (contacts, groups, etc.)
- Select which DT fields to include in forms
- Add custom fields (text, textarea, dropdown, toggle)
- Preview forms before publishing
- Generate embed codes for forms

### 2. Form Builder

#### Field Configuration
- **DT Fields Integration**: Automatically pull available fields from `DT_Posts::get_post_field_settings()` for the selected post type
- **Field Types Supported**:
  - Text fields (`text`)
  - Text areas (`textarea`) 
  - Dropdowns (`key_select`)
  - Multi-select (`multi_select`)
  - Date fields (`date`)
  - Number fields (`number`)
  - Boolean/Toggle fields (`boolean`)
  - Communication channels (`communication_channel`)
  - Location fields (`location`)

#### Custom Field Support
- Text input fields
- Textarea fields
- Dropdown select fields
- Toggle/checkbox fields
- Custom fields are stored as comments on the created DT record

#### Form Configuration Options
- Form title and description
- Success message customization
- Field validation rules
- Required field settings
- Field ordering and grouping

### 3. Responsive Form Rendering

#### Design Requirements
- Modern, clean, responsive design
- Mobile-first approach
- Fast loading times (minimal CSS/JS)
- Accessibility compliant (WCAG 2.1 AA)
- Cross-browser compatibility

#### Technical Implementation
- Lightweight CSS framework or custom CSS
- Progressive enhancement JavaScript
- Form validation (client-side and server-side)
- CSRF protection

#### Embedding Options
- iframe embed code generation
- Direct link sharing

### 4. API Endpoints and Security

#### Form Submission API
- `POST /wp-json/dt-webform/v1/submit/{form_id}`
- Validates submitted data against form configuration
- Creates DT records directly via `DT_Posts::create_post()`
- Handles custom fields as comments
- Returns success/error responses
- No submission logging (submissions create DT records immediately)

#### Form Configuration API
- `GET /wp-json/dt-webform/v1/form/{form_id}` - Get form configuration for rendering
- `GET /wp-json/dt-webform/v1/forms` - List all forms (admin only)
- `POST /wp-json/dt-webform/v1/forms` - Create new form (admin only)
- `PUT /wp-json/dt-webform/v1/forms/{form_id}` - Update form (admin only)
- `DELETE /wp-json/dt-webform/v1/forms/{form_id}` - Delete form (admin only)

#### Security Measures
- CSRF token validation
- Input sanitization and validation
- XSS prevention
- SQL injection prevention
- Permission checks for admin endpoints (using 'manage_dt' capability)

## Database Schema

### Webforms Storage
Webforms are stored using WordPress's existing posts and postmeta tables:

#### Custom Post Type Registration
```php
register_post_type('dt_webform', [
    'labels' => [
        'name' => 'DT Webforms',
        'singular_name' => 'DT Webform'
    ],
    'public' => false,
    'show_ui' => false,
    'supports' => ['title', 'author'],
    'capability_type' => 'dt_webform',
    'map_meta_cap' => true
]);
```

#### Form Data Storage (wp_postmeta)
- `dt_webform_config` - JSON-encoded form configuration
- `dt_webform_post_type` - Target DT post type (contacts, groups, etc.)
- `dt_webform_is_active` - Form active status (1/0)
- `dt_webform_fields` - JSON-encoded field definitions
- `dt_webform_settings` - JSON-encoded form settings (title, description, etc.)

#### Example Usage
```php
// Create new webform
$form_id = wp_insert_post([
    'post_type' => 'dt_webform',
    'post_title' => 'Contact Registration Form',
    'post_status' => 'publish',
    'post_author' => get_current_user_id()
]);

// Store form configuration
update_post_meta($form_id, 'dt_webform_post_type', 'contacts');
update_post_meta($form_id, 'dt_webform_is_active', 1);
update_post_meta($form_id, 'dt_webform_config', json_encode($form_config));
```

## File Structure

```
dt-webform/
├── dt-webform.php                    # Main plugin file
├── admin/
│   ├── admin-menu-and-tabs.php       # Admin menu structure
│   ├── class-form-builder.php        # Form builder interface
│   ├── class-form-manager.php        # Form CRUD operations
│   └── assets/
│       ├── css/
│       │   └── admin.css             # Admin interface styles
│       └── js/
│           ├── form-builder.js       # Form builder functionality
│           └── form-preview.js       # Form preview functionality
├── includes/
│   ├── class-dt-webform-core.php     # Core plugin functionality
│   ├── class-form-processor.php      # Form submission processing
│   └── class-form-validator.php      # Form validation logic
├── public/
│   ├── class-form-renderer.php       # Form rendering logic
│   ├── form-template.php             # Form HTML template
│   └── assets/
│       ├── css/
│       │   └── form.css              # Public form styles
│       └── js/
│           └── form.js               # Public form functionality
├── rest-api/
│   ├── rest-api.php                  # API endpoint registration
│   ├── class-forms-controller.php    # Forms CRUD API
│   └── class-submission-controller.php # Form submission API
└── languages/                        # Translation files
```

## Implementation Phases

### Phase 1: Core Infrastructure
1. Custom post type registration for webforms
2. Basic form model and CRUD operations using WordPress posts/postmeta
3. Admin menu structure and basic UI
4. REST API foundation

### Phase 2: Form Builder
1. Form builder interface
2. DT field integration and mapping
3. Custom field support
4. Form configuration validation
5. Form preview functionality

### Phase 3: Form Rendering
1. Public form rendering system
2. Responsive form templates
3. Client-side validation
4. Form submission handling
5. Success/error messaging

### Phase 4: Advanced Features
1. Form analytics and reporting
2. Export/import functionality
3. Form templates and presets
4. Advanced field types
5. Conditional field logic

### Phase 5: Polish and Optimization
1. Performance optimization
2. Security hardening
3. Accessibility improvements
4. Comprehensive testing
5. Documentation completion

## User Stories

### Admin User Stories
1. **As an admin**, I want to create a new webform so that I can collect contact information from my website visitors.
2. **As an admin**, I want to select which DT fields appear in my form so that I collect only relevant information.
3. **As an admin**, I want to add custom fields to my form so that I can collect information not covered by standard DT fields.
4. **As an admin**, I want to preview my form before publishing so that I can ensure it looks and functions correctly.
5. **As an admin**, I want to get an embed code for my form so that I can add it to external websites.

### End User Stories
1. **As a website visitor**, I want to fill out a form quickly so that I can submit my information without hassle.
2. **As a mobile user**, I want the form to work well on my phone so that I can submit information on any device.
3. **As a form submitter**, I want to receive confirmation so that I know my submission was successful.
4. **As a user with disabilities**, I want the form to be accessible so that I can use it with assistive technologies.

## Technical Requirements

### WordPress Requirements
- WordPress 5.0+
- PHP 7.4+
- Disciple.Tools theme active
- `manage_dt` capability for admin access

### Performance Requirements
- Form pages load in under 3 seconds
- CSS/JS assets under 100KB combined
- Efficient WordPress post queries for form retrieval
- Caching support for form configurations

### Security Requirements
- All inputs sanitized and validated
- CSRF protection on all forms
- SQL injection prevention
- XSS prevention
- Secure headers implementation

### Browser Support
- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Integration Points

### Disciple.Tools Integration
- Uses `DT_Posts::get_post_field_settings()` for field definitions
- Creates records via `DT_Posts::create_post()`
- Integrates with DT permission system
- Follows DT coding standards and conventions
- Uses DT notification system for form submissions

### WordPress Integration
- Follows WordPress coding standards
- Uses WordPress admin UI conventions
- Integrates with WordPress user system
- Supports WordPress multisite
- Compatible with common WordPress caching plugins

## Success Metrics

### Functionality Metrics
- Forms can be created and configured successfully
- Form submissions create DT records accurately
- Forms render correctly across devices and browsers
- API endpoints respond within acceptable time limits

### Usability Metrics
- Admin users can create forms without documentation
- Form submission process completed in under 2 minutes
- Error messages are clear and actionable
- Form builder is intuitive and efficient

### Performance Metrics
- Form pages load under 3 seconds
- API response times under 1 second
- No memory leaks or excessive resource usage
- Handles concurrent form submissions gracefully

## Future Enhancements

### Advanced Form Features
- Conditional field logic
- Multi-page forms
- File upload support
- Integration with payment processors
- Form templates and themes

### Analytics and Reporting
- Conversion tracking
- A/B testing support
- Detailed submission analytics
- Export capabilities

### Integration Opportunities
- CRM integrations
- Email marketing platform connections
- Social media integrations
- Third-party form builders

This specification provides the foundation for building a comprehensive, secure, and user-friendly webform plugin for Disciple.Tools that meets the needs of both administrators and end users while maintaining high standards for performance, security, and usability.

## Maybe/Future Considerations

The following features were considered but may be implemented in future versions:

### Analytics and Monitoring
- View form submission statistics
- Form performance analytics
- Conversion tracking
- Form submission logging table

### Advanced Security
- Rate limiting per IP (10 submissions per minute per IP)
- Advanced rate limiting strategies

### Enhanced Integration
- WordPress shortcode support (for DT sites)
- Redirect URL after submission (instead of success message)

### Additional Form Features
- Multi-step forms
- Conditional field logic
- File upload capabilities
- Integration with third-party services 