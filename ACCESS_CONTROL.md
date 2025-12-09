# EventUp - Access Control & Role-Based Authorization

## Overview

EventUp implements a role-based access control system with three user types:

1. **Regular Users** - Can browse and register for events
2. **Organizers** - Can manage events they're assigned to
3. **Admins** - Can fully manage events they created (delete, invite organizers, manage tasks)

## Database Structure

### User Roles
- Users are stored in the `users` table
- Event roles are stored in the `event_roles` table with two roles:
  - `admin` - Full control over an event (can delete, invite organizers, manage tasks)
  - `organizer` - Can manage event details and tasks assigned to them

## Page Access Control

### Public Pages (No Login Required)
- **index.php** - Browse upcoming events
- **login.php** - User login
- **register.php** - User registration
- **event_details.php** - View event details and register (optional login)

### User Pages (Login Required)
- **dashboard.php** - User dashboard
  - Regular users see event browsing options
  - Organizers are automatically redirected to `organizer_dashboard.php`
- **logout.php** - Logout functionality

### Organizer Pages (Login + Organizer Role Required)
- **organizer_dashboard.php** - View all events where user is admin/organizer
- **create_event.php** - Create new events (creator becomes admin)
- **edit_event.php** - Edit event details (organizer/admin only)
- **my_tasks.php** - View tasks assigned to the user
- **register_from_invitation.php** - Accept organizer invitations

### Admin Pages (Login + Admin Role for Specific Event Required)
- **manage_tasks.php** - Create and manage tasks for an event (admin only)
- **invite_organizers.php** - Invite organizers to an event (admin only)

## Helper Functions (role_check.php)

The `role_check.php` file provides utility functions for access control:

### Authentication Functions
- `requireLogin()` - Redirects to login if user is not authenticated
- `requireOrganizer()` - Redirects to dashboard if user is not an organizer

### Role Checking Functions
- `isOrganizer($user_id)` - Returns true if user is organizer/admin for any event
- `isEventAdmin($user_id, $event_id)` - Returns true if user is admin of specific event
- `isEventOrganizer($user_id, $event_id)` - Returns true if user is organizer/admin of specific event
- `getUserEventRole($user_id, $event_id)` - Returns user's role ('admin', 'organizer', or null)

### Authorization Functions
- `requireEventOrganizer($user_id, $event_id)` - Redirects if user is not event organizer
- `requireEventAdmin($user_id, $event_id)` - Redirects if user is not event admin

## Navigation

The header navigation dynamically shows:
- **All Users**: "Browse Events" link
- **Logged-in Users**: "Dashboard" link
- **Organizers**: "My Events" and "+ Create Event" links
- **All Users**: Login/Register or Logout links based on authentication status

## Event Creation Flow

1. User logs in → redirected to dashboard
2. If user is organizer → redirected to organizer_dashboard
3. User clicks "+ Create Event" → create_event.php
4. Event is created with user as `admin` in event_roles table
5. User can now manage the event

## Organizer Invitation Flow

1. Event admin visits "Invite Organizers" page
2. Admin enters email addresses of users to invite
3. If user exists → added as `organizer` in event_roles table
4. If user doesn't exist → invitation token created in event_invitations table
5. Invited user can register using the invitation link

## Task Management Flow

1. Event admin visits "Manage Tasks" page
2. Admin creates tasks and assigns them to organizers
3. Organizers see assigned tasks in "My Tasks" page
4. Organizers can update task status (pending → in_progress → completed)

## Security Considerations

1. **Database Connections**: All pages use `getDatabaseConnection()` from database.php
2. **Prepared Statements**: All queries use parameterized statements to prevent SQL injection
3. **Session Management**: User ID and name stored in session after login
4. **Role Verification**: Every protected page verifies user's role before allowing access
5. **Event-Specific Permissions**: Users can only manage events they're assigned to
6. **Cascading Deletes**: Database schema uses foreign key constraints with cascading deletes

## Testing Access Control

### Test Case 1: Regular User
1. Register new account
2. Login → should see dashboard with "Browse Events" option
3. Try to access `/create_event.php` → should see error or redirect
4. Browse events and register for one

### Test Case 2: Organizer
1. Create an event → automatically becomes admin
2. Login → should see "My Events" and "+ Create Event" in navigation
3. Access organizer_dashboard → should see created event
4. Click "Invite Organizers" → should be able to invite other users
5. Click "Manage Tasks" → should be able to create tasks

### Test Case 3: Non-Admin Organizer
1. Get invited to an event as organizer
2. Accept invitation → added to event_roles as organizer
3. Access organizer_dashboard → should see the event
4. Try to access manage_tasks.php → should be redirected (not admin)
5. Can edit event details but cannot delete or manage tasks

## Future Enhancements

- User profile management page
- Event attendance history
- Task completion notifications
- Role-based dashboard customization
- Admin panel for system administrators
