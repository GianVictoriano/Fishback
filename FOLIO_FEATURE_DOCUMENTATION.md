# Literary Folio Feature - Complete Documentation

## Overview

The Literary Folio feature allows the publication team to create themed collections for literary works (poems, short stories, essays, etc.). It supports both journalist-only submissions and school-wide submissions.

## Database Structure

### Tables Created

1. **folios** - Main folio information
2. **folio_members** - Team members managing the folio
3. **folio_submissions** - Literary works submitted to folios

### Migrations

Run the migrations:
```bash
php artisan migrate
```

This will create:
- `2025_10_16_000001_create_folios_table.php`
- `2025_10_16_000002_create_folio_members_table.php`
- `2025_10_16_000003_create_folio_submissions_table.php`

## Database Schema

### folios Table
```
- id (bigint, primary key)
- title (string) - Name of the folio
- theme (string) - Theme/topic of the collection
- lead_organizer_id (foreign key -> users.id)
- is_journalists_only (boolean) - Restricts submissions
- status (enum: draft, open, closed, published)
- timestamps
```

### folio_members Table
```
- id (bigint, primary key)
- folio_id (foreign key -> folios.id)
- user_id (foreign key -> users.id)
- timestamps
- unique constraint on (folio_id, user_id)
```

### folio_submissions Table
```
- id (bigint, primary key)
- folio_id (foreign key -> folios.id)
- user_id (foreign key -> users.id)
- title (string) - Title of the work
- content (text) - The literary work content
- type (enum: poem, short_story, essay, article, other)
- status (enum: pending, approved, rejected, revision_requested)
- feedback (text, nullable) - Reviewer feedback
- submitted_at (timestamp, nullable)
- reviewed_at (timestamp, nullable)
- reviewed_by (foreign key -> users.id, nullable)
- timestamps
```

## Models

### Folio Model
**Location:** `app/Models/Folio.php`

**Relationships:**
- `leadOrganizer()` - BelongsTo User
- `members()` - BelongsToMany User
- `submissions()` - HasMany FolioSubmission

**Methods:**
- `hasMember($userId)` - Check if user is a member
- `isLeadOrganizer($userId)` - Check if user is lead organizer
- `canUserSubmit($user)` - Check submission permissions

### FolioSubmission Model
**Location:** `app/Models/FolioSubmission.php`

**Relationships:**
- `folio()` - BelongsTo Folio
- `user()` - BelongsTo User (submitter)
- `reviewer()` - BelongsTo User (reviewer)

## API Endpoints

### Folio Management

#### Get All Folios
```
GET /api/folios
```
**Query Parameters:**
- `status` (optional) - Filter by status: draft, open, closed, published
- `is_journalists_only` (optional) - Filter by audience type

**Response:**
```json
[
  {
    "id": 1,
    "title": "Spring 2025 Literary Collection",
    "theme": "Nature and Renewal",
    "lead_organizer_id": 5,
    "is_journalists_only": true,
    "status": "open",
    "lead_organizer": {
      "id": 5,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "members": [...]
  }
]
```

#### Create Folio
```
POST /api/folios
```
**Request Body:**
```json
{
  "title": "Spring 2025 Literary Collection",
  "theme": "Nature and Renewal",
  "lead_organizer_id": 5,
  "is_journalists_only": true,
  "members": [1, 2, 3, 5]
}
```

**Response (201):**
```json
{
  "message": "Literary folio created successfully!",
  "folio": {
    "id": 1,
    "title": "Spring 2025 Literary Collection",
    ...
  }
}
```

#### Get Single Folio
```
GET /api/folios/{id}
```

#### Update Folio
```
PUT /api/folios/{id}
```
**Request Body:**
```json
{
  "title": "Updated Title",
  "status": "open",
  "members": [1, 2, 3]
}
```

#### Delete Folio
```
DELETE /api/folios/{id}
```

### Submission Management

#### Submit Work to Folio
```
POST /api/folios/{id}/submit
```
**Request Body:**
```json
{
  "title": "A Poem About Spring",
  "content": "Roses are red...",
  "type": "poem"
}
```

**Types:** `poem`, `short_story`, `essay`, `article`, `other`

**Response (201):**
```json
{
  "message": "Work submitted successfully!",
  "submission": {
    "id": 1,
    "folio_id": 1,
    "user_id": 10,
    "title": "A Poem About Spring",
    "type": "poem",
    "status": "pending",
    "submitted_at": "2025-10-16T00:00:00.000000Z"
  }
}
```

#### Get Folio Submissions
```
GET /api/folios/{id}/submissions
```

**Response:**
```json
[
  {
    "id": 1,
    "title": "A Poem About Spring",
    "content": "...",
    "type": "poem",
    "status": "pending",
    "user": {
      "id": 10,
      "name": "Jane Smith"
    },
    "submitted_at": "2025-10-16T00:00:00.000000Z"
  }
]
```

#### Review Submission
```
POST /api/folios/{folioId}/submissions/{submissionId}/review
```
**Request Body:**
```json
{
  "status": "approved",
  "feedback": "Great work! This fits the theme perfectly."
}
```

**Status Options:** `approved`, `rejected`, `revision_requested`

### Member Management

#### Add Member to Folio
```
POST /api/folios/{id}/members
```
**Request Body:**
```json
{
  "user_id": 15
}
```

#### Remove Member from Folio
```
DELETE /api/folios/{id}/members/{userId}
```

## Frontend Integration

### Create Folio Modal
**Location:** `Fishfront/app/collab/create-content.js`

**Features:**
- Title input
- Theme input
- Member selection with search
- Lead organizer selection
- Audience toggle (Journalists Only / Whole School)

**Usage:**
1. Click "Folio" card in Create Content screen
2. Fill in folio details
3. Select team members
4. Choose lead organizer from members
5. Toggle audience type
6. Click "Create Folio"

## Workflow

### Creating a Folio

1. **Admin/Editor creates folio:**
   - Sets title and theme
   - Selects team members
   - Designates lead organizer
   - Chooses audience (journalists only or whole school)
   - Status starts as "draft"

2. **Opening for submissions:**
   - Update folio status to "open"
   - Users can now submit works

3. **Submission process:**
   - Users check if they can submit (based on `is_journalists_only`)
   - Submit their literary work with title, content, and type
   - Submission status: "pending"

4. **Review process:**
   - Lead organizer or team members review submissions
   - Can approve, reject, or request revisions
   - Provide feedback to submitters

5. **Publishing:**
   - Update folio status to "published"
   - Approved submissions are included in the collection

## Permission Logic

### Who Can Submit?

**If `is_journalists_only = true`:**
- Only users with `profile.role = 'collaborator'` can submit

**If `is_journalists_only = false`:**
- Anyone can submit (whole school)

### Who Can Review?

- Lead organizer
- Folio members
- (Consider adding role-based middleware for additional control)

## Status Flow

### Folio Status
```
draft → open → closed → published
```

- **draft**: Being set up, not accepting submissions
- **open**: Accepting submissions
- **closed**: No longer accepting submissions, review in progress
- **published**: Final collection is published

### Submission Status
```
pending → approved/rejected/revision_requested
```

- **pending**: Awaiting review
- **approved**: Accepted for publication
- **rejected**: Not accepted
- **revision_requested**: Needs changes before reconsideration

## Example Usage

### Create a Folio
```javascript
const response = await apiClient.post('/folios', {
  title: "Spring 2025 Poetry Collection",
  theme: "Renewal and Growth",
  lead_organizer_id: 5,
  is_journalists_only: false, // Open to whole school
  members: [1, 2, 3, 5]
});
```

### Submit a Poem
```javascript
const response = await apiClient.post('/folios/1/submit', {
  title: "Whispers of Spring",
  content: "In gardens green where flowers bloom...",
  type: "poem"
});
```

### Review a Submission
```javascript
const response = await apiClient.post('/folios/1/submissions/5/review', {
  status: "approved",
  feedback: "Beautiful imagery! This captures the theme perfectly."
});
```

## Testing

### Test Creating a Folio
```bash
curl -X POST http://localhost:8000/api/folios \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test Folio",
    "theme": "Testing",
    "lead_organizer_id": 1,
    "is_journalists_only": true,
    "members": [1, 2]
  }'
```

### Test Submitting Work
```bash
curl -X POST http://localhost:8000/api/folios/1/submit \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test Poem",
    "content": "This is a test poem",
    "type": "poem"
  }'
```

## Future Enhancements

Potential improvements:
- File attachments for submissions
- Collaborative editing
- Version history for submissions
- Public gallery for published folios
- Export folio as PDF/ebook
- Submission deadlines
- Anonymous submissions option
- Voting/rating system for submissions
- Categories within folios
- Submission templates

## Security Considerations

1. **Authentication:** All endpoints require authentication
2. **Authorization:** Consider adding middleware to check:
   - Only lead organizer/members can review
   - Only authorized users can update folio status
3. **Validation:** All inputs are validated
4. **SQL Injection:** Using Eloquent ORM prevents SQL injection
5. **Content Moderation:** Consider adding content filtering for submissions

## Troubleshooting

### Issue: User can't submit to folio

**Check:**
1. Is the folio status "open"?
2. If `is_journalists_only = true`, does user have `profile.role = 'collaborator'`?
3. Is the user authenticated?

### Issue: Can't add member to folio

**Check:**
1. Does the user exist?
2. Is the user already a member?
3. Check the unique constraint on `folio_members` table

### Issue: Submissions not showing

**Check:**
1. Are you loading the `submissions` relationship?
2. Check the folio_id in the submissions table
3. Verify the foreign key constraints

## Database Seeder (Optional)

Create a seeder for testing:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Folio;
use App\Models\User;

class FolioSeeder extends Seeder
{
    public function run()
    {
        $leadOrganizer = User::whereHas('profile', function($q) {
            $q->where('role', 'collaborator');
        })->first();

        if ($leadOrganizer) {
            $folio = Folio::create([
                'title' => 'Spring 2025 Literary Collection',
                'theme' => 'Nature and Renewal',
                'lead_organizer_id' => $leadOrganizer->id,
                'is_journalists_only' => false,
                'status' => 'open',
            ]);

            // Add some members
            $members = User::whereHas('profile', function($q) {
                $q->where('role', 'collaborator');
            })->take(3)->pluck('id');

            $folio->members()->attach($members);
        }
    }
}
```

Run the seeder:
```bash
php artisan db:seed --class=FolioSeeder
```
