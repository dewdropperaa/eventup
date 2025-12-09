# EventUp - System Architecture

## Application Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         EVENTUP APPLICATION                      │
└─────────────────────────────────────────────────────────────────┘

                    ┌──────────────────────┐
                    │   Unauthenticated    │
                    │      User            │
                    └──────────────────────┘
                           │
                ┌──────────┴──────────┐
                │                     │
          ┌─────▼─────┐        ┌─────▼─────┐
          │  Login    │        │ Register  │
          │  login.php│        │register.php
          └─────┬─────┘        └─────┬─────┘
                │                     │
                └──────────┬──────────┘
                           │
                    ┌──────▼──────┐
                    │  auth.php   │ (Database: Verify credentials)
                    └──────┬──────┘
                           │
                ┌──────────┴──────────┐
                │                     │
          ┌─────▼──────────┐   ┌─────▼──────────┐
          │  Regular User  │   │   Organizer    │
          │  (No Roles)    │   │   (Has Roles)  │
          └─────┬──────────┘   └─────┬──────────┘
                │                     │
          ┌─────▼──────────┐   ┌─────▼──────────┐
          │  dashboard.php │   │organizer_      │
          │  (User View)   │   │dashboard.php   │
          └─────┬──────────┘   └─────┬──────────┘
                │                     │
          ┌─────▼──────────┐   ┌─────┴──────────────────┐
          │  index.php     │   │                        │
          │ (Browse Events)│   │ ┌──────────────────┐   │
          └────────────────┘   │ │ create_event.php │   │
                               │ └──────────────────┘   │
                               │                        │
                               │ ┌──────────────────┐   │
                               │ │ edit_event.php   │   │
                               │ └──────────────────┘   │
                               │                        │
                               │ ┌──────────────────┐   │
                               │ │ my_tasks.php     │   │
                               │ └──────────────────┘   │
                               │                        │
                               └────────┬───────────────┘
                                        │
                              ┌─────────▼─────────┐
                              │  Event Admin?     │
                              │  (role='admin')   │
                              └─────────┬─────────┘
                                        │
                        ┌───────────────┴───────────────┐
                        │                               │
                  ┌─────▼──────────┐           ┌─────▼──────────┐
                  │manage_tasks.php│           │invite_          │
                  │ (Admin Only)   │           │organizers.php   │
                  └────────────────┘           │ (Admin Only)    │
                                              └─────────────────┘
```

## Database Schema Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                      DATABASE TABLES                         │
└─────────────────────────────────────────────────────────────┘

┌──────────────────┐
│     USERS        │
├──────────────────┤
│ id (PK)          │
│ nom              │
│ email (UNIQUE)   │
│ mot_de_passe     │
└────────┬─────────┘
         │
         │ (1:N)
         │
    ┌────▼──────────────────┐
    │   EVENT_ROLES         │
    ├───────────────────────┤
    │ id (PK)               │
    │ user_id (FK→USERS)    │
    │ event_id (FK→EVENTS)  │
    │ role (admin/organizer)│
    └────┬──────────────────┘
         │
         │ (N:1)
         │
    ┌────▼──────────────────┐
    │      EVENTS           │
    ├───────────────────────┤
    │ id (PK)               │
    │ titre                 │
    │ description           │
    │ date                  │
    │ lieu                  │
    │ nb_max_participants   │
    └────┬──────────────────┘
         │
    ┌────┴──────────────────┐
    │ (1:N)                 │ (1:N)
    │                       │
┌───▼──────────────┐  ┌────▼──────────────────┐
│  REGISTRATIONS   │  │   TASKS               │
├──────────────────┤  ├───────────────────────┤
│ id (PK)          │  │ id (PK)               │
│ user_id (FK)     │  │ event_id (FK→EVENTS)  │
│ event_id (FK)    │  │ organizer_id (FK)     │
│ date_inscription │  │ task_name             │
└──────────────────┘  │ description           │
                      │ status                │
                      │ due_date              │
                      │ created_at            │
                      │ updated_at            │
                      └───────────────────────┘

┌──────────────────────────┐
│  EVENT_INVITATIONS       │
├──────────────────────────┤
│ id (PK)                  │
│ email                    │
│ event_id (FK→EVENTS)     │
│ token (UNIQUE)           │
│ token_expiry             │
│ created_at               │
│ used                     │
└──────────────────────────┘
```

## Access Control Layer

```
┌─────────────────────────────────────────────────────────────┐
│                   ROLE_CHECK.PHP                             │
│              (Access Control Helper Functions)               │
└─────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│ Authentication Functions                                    │
├────────────────────────────────────────────────────────────┤
│ • requireLogin()          - Enforce login requirement      │
│ • requireOrganizer()      - Enforce organizer role         │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│ Role Checking Functions                                     │
├────────────────────────────────────────────────────────────┤
│ • isOrganizer($user_id)                                    │
│   └─ Returns: true if user has any organizer/admin role    │
│                                                             │
│ • isEventAdmin($user_id, $event_id)                        │
│   └─ Returns: true if user is admin of specific event      │
│                                                             │
│ • isEventOrganizer($user_id, $event_id)                    │
│   └─ Returns: true if user is organizer/admin of event     │
│                                                             │
│ • getUserEventRole($user_id, $event_id)                    │
│   └─ Returns: 'admin', 'organizer', or null                │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│ Authorization Functions                                     │
├────────────────────────────────────────────────────────────┤
│ • requireEventOrganizer($user_id, $event_id)               │
│   └─ Redirects if user is not event organizer              │
│                                                             │
│ • requireEventAdmin($user_id, $event_id)                   │
│   └─ Redirects if user is not event admin                  │
└────────────────────────────────────────────────────────────┘
```

## Page Access Control Matrix

```
┌──────────────────────────┬────────┬───────┬──────────┬───────┐
│ Page                     │ Public │ Login │ Organizer│ Admin │
├──────────────────────────┼────────┼───────┼──────────┼───────┤
│ index.php                │   ✓    │   ✓   │    ✓     │   ✓   │
│ login.php                │   ✓    │   ✓   │    ✓     │   ✓   │
│ register.php             │   ✓    │   ✓   │    ✓     │   ✓   │
│ event_details.php        │   ✓    │   ✓   │    ✓     │   ✓   │
├──────────────────────────┼────────┼───────┼──────────┼───────┤
│ dashboard.php            │        │   ✓   │    ✓     │   ✓   │
│ logout.php               │        │   ✓   │    ✓     │   ✓   │
├──────────────────────────┼────────┼───────┼──────────┼───────┤
│ organizer_dashboard.php  │        │       │    ✓     │   ✓   │
│ create_event.php         │        │   ✓   │    ✓     │   ✓   │
│ edit_event.php           │        │   ✓   │    ✓     │   ✓   │
│ my_tasks.php             │        │   ✓   │    ✓     │   ✓   │
│ register_from_invite.php │   ✓    │   ✓   │    ✓     │   ✓   │
├──────────────────────────┼────────┼───────┼──────────┼───────┤
│ manage_tasks.php         │        │       │          │   ✓   │
│ invite_organizers.php    │        │       │          │   ✓   │
└──────────────────────────┴────────┴───────┴──────────┴───────┘
```

## Request Flow for Protected Pages

```
User Request
    │
    ▼
┌─────────────────────────────────┐
│ session_start()                 │
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│ require_once 'role_check.php'   │
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│ requireLogin()                  │
│ (Check: $_SESSION['user_id'])   │
└─────────────────────────────────┘
    │
    ├─ Not Set ──────────────────► Redirect to login.php
    │
    ▼
┌─────────────────────────────────┐
│ requireOrganizer() [if needed]  │
│ (Check: event_roles table)      │
└─────────────────────────────────┘
    │
    ├─ No Role ──────────────────► Redirect to dashboard.php
    │
    ▼
┌─────────────────────────────────┐
│ requireEventAdmin() [if needed]  │
│ (Check: role='admin' for event) │
└─────────────────────────────────┘
    │
    ├─ Not Admin ────────────────► Redirect to organizer_dashboard.php
    │
    ▼
┌─────────────────────────────────┐
│ getDatabaseConnection()         │
│ (Get PDO connection)            │
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│ Execute Page Logic              │
│ (Database queries, etc.)        │
└─────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────┐
│ Render HTML Response            │
└─────────────────────────────────┘
```

## User Role Lifecycle

```
┌──────────────────┐
│  New User        │
│  (No Roles)      │
└────────┬─────────┘
         │
         │ register.php
         │ (Create user account)
         │
         ▼
┌──────────────────┐
│  Regular User    │
│  (No Roles)      │
└────────┬─────────┘
         │
         │ create_event.php
         │ (Create event)
         │
         ▼
┌──────────────────────────────────┐
│  Organizer/Admin                 │
│  (role='admin' for created event)│
└────────┬─────────────────────────┘
         │
         ├─ invite_organizers.php
         │  (Add other users as organizers)
         │
         │  ┌──────────────────────────────┐
         │  │ User receives invitation     │
         │  │ register_from_invitation.php │
         │  │ (Accept invitation)          │
         │  │                              │
         │  └──────────────┬───────────────┘
         │                 │
         │                 ▼
         │  ┌──────────────────────────────┐
         │  │  Organizer                   │
         │  │  (role='organizer' for event)│
         │  └──────────────────────────────┘
         │
         └─ manage_tasks.php
            (Create and assign tasks)
            │
            ▼
         ┌──────────────────────────────┐
         │ Organizer receives task      │
         │ my_tasks.php                 │
         │ (Update task status)         │
         └──────────────────────────────┘
```

## Security Layers

```
┌─────────────────────────────────────────────────────────────┐
│                    SECURITY IMPLEMENTATION                   │
└─────────────────────────────────────────────────────────────┘

Layer 1: Authentication
├─ Session-based authentication
├─ Password hashing with bcrypt
├─ Credentials verified against database
└─ Session variables stored after login

Layer 2: Authorization
├─ Role-based access control
├─ Event-specific permissions
├─ Admin-only operations protected
└─ Helper functions enforce permissions

Layer 3: Data Protection
├─ Prepared statements prevent SQL injection
├─ Input validation and sanitization
├─ Output escaping with htmlspecialchars()
└─ Type casting for numeric values

Layer 4: Error Handling
├─ Try-catch blocks for database operations
├─ Error logging to server logs
├─ User-friendly error messages
└─ No sensitive information exposed

Layer 5: Database Security
├─ Foreign key constraints
├─ Cascading deletes for data integrity
├─ Unique constraints on email
└─ Proper indexing for performance
```

## Deployment Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      WEB SERVER                              │
│                    (Apache/PHP)                              │
└─────────────────────────────────────────────────────────────┘
         │
         ├─ /index.php
         ├─ /login.php
         ├─ /register.php
         ├─ /dashboard.php
         ├─ /organizer_dashboard.php
         ├─ /create_event.php
         ├─ /edit_event.php
         ├─ /event_details.php
         ├─ /manage_tasks.php
         ├─ /my_tasks.php
         ├─ /invite_organizers.php
         ├─ /register_from_invitation.php
         ├─ /auth.php
         ├─ /logout.php
         ├─ /database.php (Database connection)
         ├─ /role_check.php (Access control)
         ├─ /header.php (Navigation)
         ├─ /footer.php
         └─ /assets/ (Images, CSS, JS)
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│                    MYSQL DATABASE                            │
│                 (event_management)                           │
├─────────────────────────────────────────────────────────────┤
│ Tables:                                                      │
│ • users                                                      │
│ • events                                                     │
│ • event_roles                                                │
│ • registrations                                              │
│ • tasks                                                      │
│ • event_invitations                                          │
└─────────────────────────────────────────────────────────────┘
```
