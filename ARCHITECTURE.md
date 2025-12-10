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
                    ┌─────────┴─────────┐
                    │                   │
              ┌─────▼──────────┐ ┌─────▼──────────┐
              │manage_tasks.php│ │invite_        │
              │ (Admin Only)   │ │organizers.php │
              └────────────────┘ │ (Admin Only)  │
                                   └────────────────┘
                              │
                              ▼
                    ┌─────────┴─────────┐
                    │  Event Owner     │
                    │  (created_by)    │
                    └─────────┬─────────┘
                              │
                    ┌─────────┴─────────┐
                    │                   │
              ┌─────▼──────────┐ ┌─────▼──────────┐
              │event_         │ │budget.php     │
              │permissions.php│ │(Budget Mgmt)  │
              └────────────────┘ └────────────────┘
                              │
                              ▼
                    ┌─────────┴─────────┐
                    │                   │
              ┌─────▼──────────┐ ┌─────▼──────────┐
              │communication_  │ │resources.php  │
              │hub.php         │ │(Resource Mgmt)│
              └────────────────┘ └────────────────┘
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

┌──────────────────────────┐
│  EVENT_ORGANIZERS        │
├──────────────────────────┤
│ id (PK)                  │
│ event_id (FK→EVENTS)     │
│ user_id (FK→USERS)       │
│ role (admin/organizer)   │
│ created_at               │
└────────┬─────────────────┘
         │
         │ (1:N)
         │
┌────────▼──────────────────┐
│  EVENT_PERMISSIONS       │
├──────────────────────────┤
│ id (PK)                  │
│ event_id (FK→EVENTS)     │
│ user_id (FK→USERS)       │
│ permission_name          │
│ granted (BOOLEAN)        │
│ created_at               │
│ updated_at               │
└────────┬─────────────────┘
         │
         │ (1:N)
         │
┌────────▼──────────────────┐
│  EVENT_MESSAGES          │
├──────────────────────────┤
│ id (PK)                  │
│ event_id (FK→EVENTS)     │
│ user_id (FK→USERS)       │
│ message_text             │
│ created_at               │
└────────┬─────────────────┘
         │
         │ (1:N)
         │
┌────────▼──────────────────┐
│  EVENT_RESOURCES         │
├──────────────────────────┤
│ id (PK)                  │
│ event_id (FK→EVENTS)     │
│ nom                      │
│ type                     │
│ quantite_totale          │
│ description              │
│ date_disponibilite_debut │
│ date_disponibilite_fin   │
│ image_path               │
│ statut                   │
│ created_at               │
│ updated_at               │
└────────┬─────────────────┘
         │
         │ (1:N)
         │
┌────────▼──────────────────┐
│  RESOURCE_BOOKINGS       │
├──────────────────────────┤
│ id (PK)                  │
│ resource_id (FK)         │
│ user_id (FK→USERS)       │
│ event_id (FK→EVENTS)     │
│ date_debut               │
│ date_fin                 │
│ statut                   │
│ notes                    │
│ created_at               │
│ updated_at               │
└────────┬─────────────────┘
         │
         │ (1:N)
         │
┌────────▼──────────────────┐
│  EVENT_BUDGET_SETTINGS   │
├──────────────────────────┤
│ id (PK)                  │
│ event_id (UNIQUE FK)     │
│ budget_limit             │
│ currency                 │
│ created_at               │
│ updated_at               │
└────────┬─────────────────┘
         │
         │ (1:N)
         │
┌────────▼──────────────────┐
│  EVENT_EXPENSES          │
├──────────────────────────┤
│ id (PK)                  │
│ event_id (FK)            │
│ category                 │
│ title                    │
│ amount                   │
│ date                     │
│ notes                    │
│ created_at               │
│ updated_at               │
└────────┬─────────────────┘
         │
         │ (1:N)
         │
┌────────▼──────────────────┐
│  EVENT_INCOMES           │
├──────────────────────────┤
│ id (PK)                  │
│ event_id (FK)            │
│ source                   │
│ title                    │
│ amount                   │
│ date                     │
│ notes                    │
│ created_at               │
│ updated_at               │
└─────────────────────────┘

┌──────────────────────────┐
│  NOTIFICATIONS           │
├──────────────────────────┤
│ id (PK)                  │
│ user_id (FK→USERS)       │
│ message                  │
│ type                     │
│ related_event_id         │
│ is_read (BOOLEAN)        │
│ created_at               │
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
┌──────────────────────────┬────────┬───────┬──────────┬───────┬──────────┐
│ Page                     │ Public │ Login │ Organizer│ Admin │ Owner │
├──────────────────────────┼────────┼───────┼──────────┼───────┼──────────┤
│ index.php                │   ✓    │   ✓   │    ✓     │   ✓   │    ✓    │
│ login.php                │   ✓    │   ✓   │    ✓     │   ✓   │    ✓    │
│ register.php             │   ✓    │   ✓   │    ✓     │   ✓   │    ✓    │
│ event_details.php        │   ✓    │   ✓   │    ✓     │   ✓   │    ✓    │
├──────────────────────────┼────────┼───────┼──────────┼───────┼──────────┤
│ dashboard.php            │        │   ✓   │    ✓     │   ✓   │    ✓    │
│ logout.php               │        │   ✓   │    ✓     │   ✓   │    ✓    │
├──────────────────────────┼────────┼───────┼──────────┼───────┼──────────┤
│ organizer_dashboard.php  │        │       │    ✓     │   ✓   │    ✓    │
│ create_event.php         │        │   ✓   │    ✓     │   ✓   │    ✓    │
│ edit_event.php           │        │   ✓   │    ✓     │   ✓   │    ✓    │
│ my_tasks.php             │        │   ✓   │    ✓     │   ✓   │    ✓    │
│ register_from_invite.php │   ✓    │   ✓   │    ✓     │   ✓   │    ✓    │
├──────────────────────────┼────────┼───────┼──────────┼───────┼──────────┤
│ manage_tasks.php         │        │       │          │   ✓   │    ✓    │
│ invite_organizers.php    │        │       │          │   ✓   │    ✓    │
├──────────────────────────┼────────┼───────┼──────────┼───────┼──────────┤
│ event_permissions.php   │        │       │          │       │    ✓    │
│ budget.php               │        │       │    ✓*    │   ✓   │    ✓    │
│ communication_hub.php    │        │       │    ✓     │   ✓   │    ✓    │
│ resources.php            │        │       │    ✓     │   ✓   │    ✓    │
└──────────────────────────┴────────┴───────┴──────────┴───────┴──────────┘

* Requires can_edit_budget permission
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
│ canDo() [if needed]             │
│ (Check: granular permissions)   │
└─────────────────────────────────┘
    │
    ├─ No Permission ─────────────► Access denied / unauthorized.php
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

Layer 6: Granular Permissions
├─ Event owner automatic access
├─ Permission-based feature access
├─ canDo() helper for permission checks
├─ Default deny policy
└─ AJAX permission updates

Layer 7: Real-time Features
├─ AJAX polling for messages
├─ Dynamic notification loading
├─ Real-time conflict detection
├─ Toast notifications
└─ Chart.js visualizations
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
         ├─ /notifications.php (Notification system)
         ├─ /ajax_handler.php (AJAX endpoints)
         │
         ├─ Budget Management:
         │  ├─ /budget.php (Main interface)
         │  ├─ /add_expense.php
         │  ├─ /add_income.php
         │  ├─ /delete_expense.php
         │  ├─ /delete_income.php
         │  ├─ /update_budget_limit.php
         │  └─ /generate_budget_pdf.php
         │
         ├─ Permissions Management:
         │  ├─ /event_permissions.php (Main interface)
         │  └─ /update_event_permission.php (AJAX endpoint)
         │
         ├─ Communication Hub:
         │  ├─ /communication_hub.php (Main interface)
         │  ├─ /send_message.php (Message handler)
         │  └─ /fetch_messages.php (Message retrieval)
         │
         ├─ Resource Management:
         │  ├─ /resources.php (Main interface)
         │  ├─ /add_resource.php
         │  ├─ /edit_resource.php
         │  ├─ /get_resource.php
         │  ├─ /book_resource.php
         │  └─ /check_booking_conflict.php
         │
         └─ /assets/ (Images, CSS, JS)
            ├─ /uploads/resources/ (Resource images)
            └─ /js/main.js
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
│ • event_organizers                                           │
│ • event_permissions                                          │
│ • event_messages                                             │
│ • event_resources                                            │
│ • resource_bookings                                          │
│ • event_budget_settings                                      │
│ • event_expenses                                             │
│ • event_incomes                                              │
│ • notifications                                              │
└─────────────────────────────────────────────────────────────┘

## Feature Modules Architecture

### Budget Management System
```
┌─────────────────────────────────────────────────────────────┐
│                   BUDGET MANAGEMENT                         │
└─────────────────────────────────────────────────────────────┘

┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│  Budget Settings │    │   Expense Mgmt   │    │   Income Mgmt    │
├──────────────────┤    ├──────────────────┤    ├──────────────────┤
│ • Budget limit   │    │ • Add expenses  │    │ • Add income     │
│ • Currency       │    │ • Categories     │    │ • Sources        │
│ • Event-specific │    │ • Delete items  │    │ • Delete items   │
└────────┬─────────┘    └────────┬─────────┘    └────────┬─────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │   Visual Analytics      │
                    ├─────────────────────────┤
                    │ • Summary cards         │
                    │ • Pie charts            │
                    │ • Bar charts            │
                    │ • Budget alerts         │
                    │ • PDF export            │
                    └─────────────────────────┘
```

### Event Permissions System
```
┌─────────────────────────────────────────────────────────────┐
│                 EVENT PERMISSIONS SYSTEM                    │
└─────────────────────────────────────────────────────────────┘

┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│   Event Owner    │    │  Permission UI   │    │  Permission API  │
├──────────────────┤    ├──────────────────┤    ├──────────────────┤
│ • Auto all perms │    │ • Toggle switches │    │ • AJAX updates  │
│ • Grant/revoke   │    │ • Role badges    │    │ • Validation    │
│ • Manage team    │    │ • Real-time save │    │ • JSON responses│
└────────┬─────────┘    └────────┬─────────┘    └────────┬─────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │   Available Permissions│
                    ├─────────────────────────┤
                    │ • can_edit_budget       │
                    │ • can_manage_resources  │
                    │ • can_invite_organizers │
                    │ • can_publish_updates   │
                    └─────────────────────────┘
```

### Communication Hub System
```
┌─────────────────────────────────────────────────────────────┐
│                COMMUNICATION HUB SYSTEM                      │
└─────────────────────────────────────────────────────────────┘

┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│   Message UI     │    │  Message Handler │    │ Message Retrieval│
├──────────────────┤    ├──────────────────┤    ├──────────────────┤
│ • Chat interface │    │ • Send messages  │    │ • Fetch messages │
│ • Auto-scroll    │    │ • Validation     │    │ • Polling (2s)   │
│ • Timestamps     │    │ • Storage        │    │ • Format output  │
│ • Enter to send  │    │ • Security       │    │ • Event-specific │
└────────┬─────────┘    └────────┬─────────┘    └────────┬─────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │     Features             │
                    ├─────────────────────────┤
                    │ • Real-time messaging   │
                    │ • Organizer-only access │
                    │ • Event-specific chats  │
                    │ • XSS prevention        │
                    │ • SQL injection protection│
                    └─────────────────────────┘
```

### Resource Management System
```
┌─────────────────────────────────────────────────────────────┐
│               RESOURCE MANAGEMENT SYSTEM                     │
└─────────────────────────────────────────────────────────────┘

┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│  Resource Mgmt   │    │  Booking System  │    │  Statistics      │
├──────────────────┤    ├──────────────────┤    ├──────────────────┤
│ • Create resources│    │ • Book resources │    │ • Usage rates    │
│ • Edit resources │    │ • Conflict detect│    │ • Availability  │
│ • Delete resources│    │ • Cancel bookings│    │ • Most booked   │
│ • Image upload   │    │ • Status tracking│    │ • Status dist.  │
└────────┬─────────┘    └────────┬─────────┘    └────────┬─────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │     Features             │
                    ├─────────────────────────┤
                    │ • Resource categories   │
                    │ • Quantity management   │
                    │ • Date availability     │
                    │ • Real-time conflicts   │
                    │ • File upload security  │
                    │ • Admin/organizer access│
                    └─────────────────────────┘
```

### Notification System
```
┌─────────────────────────────────────────────────────────────┐
│                NOTIFICATION SYSTEM                           │
└─────────────────────────────────────────────────────────────┘

┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│  Notification UI │    │ Notification API │    │ Notification DB  │
├──────────────────┤    ├──────────────────┤    ├──────────────────┤
│ • Bell icon      │    │ • Create notifications│ │ • Store messages │
│ • Dropdown       │    │ • Mark as read   │    │ • User-specific  │
│ • Dynamic load   │    │ • Get unread     │    │ • Type-based     │
│ • Count badge    │    │ • Event linking  │    │ • Read status    │
└────────┬─────────┘    └────────┬─────────┘    └────────┬─────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │     Triggers             │
                    ├─────────────────────────┤
                    │ • Event registration    │
                    │ • Task assignment       │
                    │ • Organizer invitations  │
                    │ • Budget updates         │
                    │ • Resource bookings     │
                    └─────────────────────────┘
```
