# ADOMee$ - Role-Based and Attribute-Based Access Control System

ADOMee$ is a comprehensive access control system that implements both Role-Based Access Control (RBAC) and Attribute-Based Access Control (ABAC) to provide flexible and secure access management for your applications.

## Features

### Role-Based Access Control (RBAC)
- **User Roles**: Predefined roles (Admin, Sales, Editor, Operator, Client)
- **Role Assignment**: Dynamic role assignment and management
- **Permission Inheritance**: Hierarchical role structure with permission inheritance
- **Role-Based Views**: Customized dashboard views based on user roles

### Attribute-Based Access Control (ABAC)
- **Dynamic Access Rules**: Access control based on user attributes and environmental conditions
- **Context-Aware Permissions**: Time-based, location-based, and resource-based access control
- **Flexible Policy Definition**: Define complex access rules using multiple attributes

### Security Features
- **Secure Authentication**: Argon2id password hashing
- **SQL Injection Prevention**: Input sanitization and prepared statements
- **CSRF Protection**: Token-based Cross-Site Request Forgery protection
- **Data Encryption**: AES-256-GCM encryption for sensitive data
- **Session Management**: Secure session handling and timeout

## System Architecture

### User Roles
1. **Admin**
   - Full system access
   - User management
   - Role assignment
   - System configuration

2. **Sales**
   - Client management
   - Sales tracking
   - Order processing
   - Editor assignment

3. **Editor**
   - Content management
   - Task assignment
   - Quality control
   - Sales agent coordination

4. **Operator**
   - Task execution
   - Status updates
   - Resource management

5. **Client**
   - View own data
   - Place orders
   - Track progress
   - Communication

### Access Control Implementation
- **RBAC Layer**: Core role-based permissions
- **ABAC Layer**: Attribute-based policy enforcement
- **Policy Engine**: Rule evaluation and decision making
- **Audit Logging**: Access control event tracking

## Setup with XAMPP

1. **Requirements**
   - XAMPP with PHP 7.4 or higher
   - MySQL (included in XAMPP)
   - Apache (included in XAMPP)
   - OpenSSL extension (included in XAMPP)

2. **Configuration**
   - Place the project in your XAMPP's htdocs directory
   - Ensure your MySQL database is running
   - Update database credentials in `php/db.php`
   - Configure security settings in `php/security_utils.php`

## Usage

### User Management
```php
// Example: Creating a new user with role
$user = new User();
$user->create([
    'username' => 'john_doe',
    'password' => 'secure_password',
    'role' => 'editor'
]);
```

### Access Control
```php
// Example: Checking permissions
if ($rbac->hasPermission('edit_content')) {
    // Allow content editing
}

// Example: Attribute-based access
if ($abac->evaluate($user, $resource, $action)) {
    // Allow access based on attributes
}
```

## Security Best Practices

1. **Password Security**
   - Use strong password policies
   - Implement password expiration
   - Enable two-factor authentication

2. **Session Security**
   - Use secure session handling
   - Implement session timeout
   - Prevent session fixation

3. **Data Protection**
   - Encrypt sensitive data
   - Use prepared statements
   - Implement input validation

4. **Access Control**
   - Follow principle of least privilege
   - Regular permission audits
   - Monitor access patterns

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support, please contact:
- Email: g1galba042804@gmail.com

## Acknowledgments

- PHP Security Consortium
- OWASP Security Guidelines
- NIST RBAC Standard
- XACML ABAC Model 