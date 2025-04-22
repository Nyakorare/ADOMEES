# ADOMee$ - Role-Based Access Control System

ADOMee$ is a comprehensive access control system that implements Role-Based Access Control (RBAC) to provide secure and flexible access management for your applications.

## Features

### User Management
- **User Registration**: Secure user registration with email validation
- **User Authentication**: Secure login system with session management
- **Role Management**: Dynamic role assignment and management
- **User Search**: Advanced search and filtering capabilities
- **User Deletion**: Secure user deletion with confirmation

### Role-Based Access Control (RBAC)
- **Predefined Roles**: 
  - Admin: Full system access and user management
  - Sales: Client management and sales tracking
  - Editor: Content management and task assignment
  - Operator: Task execution and status updates
  - Client: View own data and place orders
- **Role-Specific Views**: Customized dashboard views based on user roles
- **Dynamic Role Assignment**: Admin can modify user roles
- **Role-Based Permissions**: Granular access control based on user roles

### User Interface
- **Modern Design**: Clean and intuitive interface
- **Dark Mode**: System-wide dark mode support
- **Responsive Layout**: Mobile-friendly design
- **Interactive Elements**: Animated components and transitions
- **Real-time Updates**: Dynamic content updates without page reload

### Security Features
- **Secure Authentication**: Password hashing using PHP's built-in functions
- **SQL Injection Prevention**: Prepared statements and parameter binding
- **Session Security**: Secure session handling and validation
- **Input Validation**: Comprehensive input sanitization
- **CSRF Protection**: Token-based protection against cross-site request forgery

## System Architecture

### User Roles and Permissions
1. **Admin**
   - Manage all users (create, edit, delete)
   - Assign and modify user roles
   - Access system-wide settings
   - View all system data

2. **Sales**
   - View and manage available clients
   - Track current clients
   - Assign editors to tasks
   - Monitor sales progress

3. **Editor**
   - Manage assigned tasks
   - Coordinate with sales agents
   - Assign tasks to operators
   - Quality control

4. **Operator**
   - Execute assigned tasks
   - Update task status
   - View task details
   - Manage resources

5. **Client**
   - View personal data
   - Track order progress
   - Place new orders
   - Communicate with assigned sales agent

### Database Structure
- **Users Table**: Stores user credentials and role information
- **Clients Table**: Manages client information and status
- **Files Table**: Tracks file assignments and status
- **Role Changes Log**: Records role modification history
- **User Deletions Log**: Tracks user deletion events

## Setup with XAMPP

1. **Requirements**
   - XAMPP with PHP 7.4 or higher
   - MySQL (included in XAMPP)
   - Apache (included in XAMPP)
   - OpenSSL extension (included in XAMPP)

2. **Installation**
   ```bash
   # Clone the repository
   git clone https://github.com/Nyakorare/ADOMEES.git
   
   # Move to XAMPP's htdocs directory
   mv adomees /path/to/xampp/htdocs/
   ```

3. **Configuration**
   - Update database credentials in `php/db.php`:
     ```php
     $servername = "localhost";
     $username = "your_username";
     $password = "your_password";
     $dbname = "adomees";
     ```
   - Import the database schema from `database/schema.sql`
   - Configure Apache virtual host if needed

4. **Security Setup**
   - Set proper file permissions
   - Configure SSL certificate for HTTPS
   - Update session configuration in `php.ini`

## Usage

### User Management
```php
// Example: Creating a new user
$user = new User();
$user->create([
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'password' => 'secure_password',
    'role' => 'client'
]);

// Example: Updating user role
$user->updateRole($user_id, 'editor');

// Example: Deleting a user
$user->delete($user_id);
```

### Access Control
```php
// Example: Checking user role
if ($_SESSION['role'] === 'admin') {
    // Grant admin access
}

// Example: Role-based view rendering
switch ($_SESSION['role']) {
    case 'admin':
        // Show admin dashboard
        break;
    case 'sales':
        // Show sales dashboard
        break;
    // ... other roles
}
```

## Security Best Practices

1. **Authentication**
   - Use strong password policies (minimum 6 characters)
   - Implement secure password hashing
   - Enable session timeout
   - Prevent session fixation

2. **Authorization**
   - Follow principle of least privilege
   - Validate user roles on every request
   - Implement role-based access control
   - Log all access attempts

3. **Data Protection**
   - Use prepared statements for all database queries
   - Sanitize all user input
   - Implement CSRF protection
   - Encrypt sensitive data

4. **Session Management**
   - Use secure session handling
   - Implement session timeout
   - Regenerate session ID on login
   - Clear session data on logout

## Support

For support, please contact:
- Email: g1galba042804@gmail.com
- GitHub Issues: https://github.com/yourusername/adomees/issues

## License

This project is licensed under the Apache License 2.0 - see the [LICENSE.md](LICENSE.md) file for details.