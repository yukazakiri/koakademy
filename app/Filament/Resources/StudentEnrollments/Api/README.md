# Student Enrollment API Documentation

This API provides comprehensive access to KoAkademy enrollment data and mirrors the capabilities available in the Filament resource interface.

> Note: Enrollment statuses are configurable from system settings. Sample values below (`Pending`, `Verified By Dept Head`, `Verified By Cashier`) are defaults.

## Base URL
```
/api/enrollments
```

## Authentication
All endpoints require Sanctum authentication. Include the bearer token in the Authorization header:
```
Authorization: Bearer {your-token}
```

---

## Endpoints

### 1. List Enrollments
Get a paginated list of enrollments with extensive filtering options.

**Endpoint:** `GET /api/enrollments`

**Query Parameters:**
- `current_period` (boolean) - Filter by current academic period (default: false)
- `school_year` (string) - Filter by school year (e.g., "2024 - 2025")
- `semester` (integer) - Filter by semester (1 or 2)
- `student_id` (string) - Filter by student ID
- `course_id` (integer) - Filter by course ID
- `status` (string) - Filter by enrollment status
- `search` (string) - Search by ID, student ID, or student name
- `with_trashed` (boolean) - Include soft-deleted records
- `with_transactions` (boolean) - Include enrollment transactions
- `sort_by` (string) - Sort field (default: created_at)
- `sort_direction` (string) - Sort direction (asc/desc, default: desc)
- `per_page` (integer) - Items per page (1-100, default: 15)

**Example Request:**
```bash
curl -X GET "https://api.koakademy.edu/api/enrollments?current_period=true&per_page=20" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Example Response:**
```json
{
  "data": [
    {
      "id": 1,
      "student_id": "202401234",
      "course_id": 1,
      "status": "Pending",
      "semester": 1,
      "academic_year": 1,
      "school_year": "2024 - 2025",
      "downpayment": 5000.00,
      "remarks": null,
      "created_at": "2024-08-15T08:30:00.000000Z",
      "updated_at": "2024-08-15T08:30:00.000000Z",
      "deleted_at": null,
      "student": {
        "id": 202401234,
        "full_name": "Juan Dela Cruz",
        "first_name": "Juan",
        "last_name": "Dela Cruz",
        "middle_name": "Santos",
        "email": "juan.delacruz@example.com",
        "academic_year": 1,
        "formatted_academic_year": "1st Year",
        "gender": "Male",
        "birth_date": "2005-05-15",
        "student_type": "College",
        "lrn": null,
        "course": {
          "id": 1,
          "code": "BSIT",
          "title": "Bachelor of Science in Information Technology"
        }
      },
      "course": {
        "id": 1,
        "code": "BSIT",
        "title": "Bachelor of Science in Information Technology",
        "lec_per_unit": 250.00,
        "lab_per_unit": 500.00,
        "miscellaneous_fee": 3500.00
      },
      "subjects_enrolled": [
        {
          "id": 1,
          "subject_id": 10,
          "class_id": 5,
          "is_modular": false,
          "enrolled_lecture_units": 3,
          "enrolled_laboratory_units": 1,
          "lecture_fee": 1000.00,
          "laboratory_fee": 500.00,
          "school_year": "2024 - 2025",
          "semester": 1,
          "academic_year": 1,
          "subject": {
            "id": 10,
            "code": "IT 111",
            "title": "Introduction to Computing",
            "lecture": 3,
            "laboratory": 1,
            "units": 4,
            "pre_requisite": []
          },
          "class": {
            "id": 5,
            "section": "A",
            "maximum_slots": 40,
            "faculty": {
              "id": 3,
              "full_name": "Prof. Maria Santos"
            },
            "schedule": [
              {
                "id": 12,
                "day_of_week": "Monday",
                "start_time": "08:00:00",
                "end_time": "10:00:00",
                "room": {
                  "id": 2,
                  "name": "Room 201"
                }
              }
            ]
          }
        }
      ],
      "tuition": {
        "id": 1,
        "discount": 0,
        "total_lectures": 15000.00,
        "total_laboratory": 2500.00,
        "total_tuition": 17500.00,
        "total_miscelaneous_fees": 3500.00,
        "overall_tuition": 21000.00,
        "downpayment": 5000.00,
        "total_balance": 16000.00
      },
      "additional_fees": {
        "items": [
          {
            "id": 1,
            "fee_name": "Late Enrollment Fee",
            "amount": 500.00,
            "description": "Applied for late enrollment"
          }
        ],
        "total": 500.00
      },
      "transactions": [
        {
          "id": 45,
          "transaction_number": "TXN-2024-0045",
          "invoicenumber": "OR-12345",
          "description": "Payment for enrollment (2024 - 2025, Semester 1)",
          "status": "completed",
          "transaction_date": "2024-08-15T10:00:00.000000Z",
          "settlements": {
            "registration_fee": 1000,
            "tuition_fee": 4000,
            "miscelanous_fee": 0
          },
          "total_amount": 5000.00,
          "created_at": "2024-08-15T10:00:00.000000Z",
          "updated_at": "2024-08-15T10:00:00.000000Z"
        }
      ],
      "resources": [
        {
          "id": 1,
          "type": "assessment",
          "file_name": "assessment-1.pdf",
          "file_path": "enrollments/1/assessment-1.pdf",
          "file_size": 245678,
          "mime_type": "application/pdf",
          "disk": "public",
          "created_at": "2024-08-15T08:35:00.000000Z"
        }
      ],
      "assessment_url": "https://api.koakademy.edu/storage/enrollments/1/assessment-1.pdf",
      "certificate_url": null,
      "statistics": {
        "total_units": 22,
        "total_lecture_units": 18,
        "total_laboratory_units": 4,
        "subjects_count": 6,
        "total_paid": 5000.00
      }
    }
  ],
  "links": {
    "first": "https://api.koakademy.edu/api/enrollments?page=1",
    "last": "https://api.koakademy.edu/api/enrollments?page=10",
    "prev": null,
    "next": "https://api.koakademy.edu/api/enrollments?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "path": "https://api.koakademy.edu/api/enrollments",
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

---

### 2. Create Enrollment
Create a new student enrollment.

**Endpoint:** `POST /api/enrollments`

**Request Body:**
```json
{
  "student_id": "202401234",
  "course_id": 1,
  "semester": 1,
  "academic_year": 1,
  "remarks": "Regular enrollment",
  "subjects": [
    {
      "subject_id": 10,
      "class_id": 5,
      "is_modular": false
    },
    {
      "subject_id": 11,
      "class_id": 6,
      "is_modular": false
    }
  ]
}
```

**Example Request:**
```bash
curl -X POST "https://api.koakademy.edu/api/enrollments" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "student_id": "202401234",
    "course_id": 1,
    "semester": 1,
    "academic_year": 1,
    "subjects": [
      {
        "subject_id": 10,
        "class_id": 5
      }
    ]
  }'
```

**Success Response (201):**
```json
{
  "message": "Enrollment created successfully",
  "data": {
    "id": 150,
    "student_id": "202401234",
    "course_id": 1,
    "status": "Pending",
    "semester": 1,
    "academic_year": 1,
    "school_year": "2024 - 2025",
    ...
  }
}
```

---

### 3. Get Single Enrollment
Retrieve detailed information about a specific enrollment.

**Endpoint:** `GET /api/enrollments/{id}`

**Query Parameters:**
- `with_trashed` (boolean) - Include if soft-deleted
- `with_transactions` (boolean) - Include enrollment transactions

**Example Request:**
```bash
curl -X GET "https://api.koakademy.edu/api/enrollments/1?with_transactions=true" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Success Response (200):**
```json
{
  "id": 1,
  "student_id": "202401234",
  ...
  (Same structure as list response)
}
```

---

### 4. Update Enrollment
Update an existing enrollment.

**Endpoint:** `PUT /api/enrollments/{id}` or `PATCH /api/enrollments/{id}`

**Request Body:**
```json
{
  "status": "Verified By Dept Head",
  "remarks": "Approved by department head"
}
```

**Example Request:**
```bash
curl -X PUT "https://api.koakademy.edu/api/enrollments/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "status": "Verified By Dept Head",
    "remarks": "Approved"
  }'
```

**Success Response (200):**
```json
{
  "message": "Enrollment updated successfully",
  "data": {
    "id": 1,
    ...
  }
}
```

---

### 5. Delete Enrollment (Soft Delete)
Soft delete an enrollment.

**Endpoint:** `DELETE /api/enrollments/{id}`

**Example Request:**
```bash
curl -X DELETE "https://api.koakademy.edu/api/enrollments/1" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Success Response (200):**
```json
{
  "message": "Enrollment deleted successfully"
}
```

---

### 6. Restore Enrollment
Restore a soft-deleted enrollment.

**Endpoint:** `POST /api/enrollments/{id}/restore`

**Example Request:**
```bash
curl -X POST "https://api.koakademy.edu/api/enrollments/1/restore" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Success Response (200):**
```json
{
  "message": "Enrollment restored successfully",
  "data": {
    "id": 1,
    ...
  }
}
```

---

### 7. Force Delete Enrollment
Permanently delete an enrollment.

**Endpoint:** `DELETE /api/enrollments/{id}/force`

**Example Request:**
```bash
curl -X DELETE "https://api.koakademy.edu/api/enrollments/1/force" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Success Response (200):**
```json
{
  "message": "Enrollment permanently deleted"
}
```

---

### 8. Get Enrollment Statistics
Get aggregated statistics about enrollments.

**Endpoint:** `GET /api/enrollments/statistics/summary`

**Query Parameters:**
- `current_period` (boolean) - Filter by current academic period (default: true)

**Example Request:**
```bash
curl -X GET "https://api.koakademy.edu/api/enrollments/statistics/summary" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Success Response (200):**
```json
{
  "total_enrollments": 450,
  "by_status": {
    "Pending": 150,
    "Verified By Dept Head": 200,
    "Verified By Cashier": 100
  },
  "by_semester": {
    "1": 250,
    "2": 200
  },
  "by_academic_year": {
    "1": 120,
    "2": 110,
    "3": 105,
    "4": 115
  },
  "current_school_year": "2024 - 2025",
  "current_semester": 1
}
```

---

### 9. Get Enrollment Schedule
Retrieve the class schedule for a specific enrollment.

**Endpoint:** `GET /api/enrollments/{id}/schedule`

**Example Request:**
```bash
curl -X GET "https://api.koakademy.edu/api/enrollments/1/schedule" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Success Response (200):**
```json
{
  "enrollment_id": 1,
  "student": "Juan Dela Cruz",
  "schedule": [
    {
      "subject": {
        "code": "IT 111",
        "title": "Introduction to Computing"
      },
      "class": {
        "section": "A",
        "faculty": "Prof. Maria Santos"
      },
      "day_of_week": "Monday",
      "start_time": "08:00:00",
      "end_time": "10:00:00",
      "room": "Room 201"
    },
    {
      "subject": {
        "code": "IT 111",
        "title": "Introduction to Computing"
      },
      "class": {
        "section": "A",
        "faculty": "Prof. Maria Santos"
      },
      "day_of_week": "Wednesday",
      "start_time": "08:00:00",
      "end_time": "10:00:00",
      "room": "Room 201"
    }
  ]
}
```

---

### 10. Get Enrollment Assessment
Retrieve tuition and fee assessment details.

**Endpoint:** `GET /api/enrollments/{id}/assessment`

**Example Request:**
```bash
curl -X GET "https://api.koakademy.edu/api/enrollments/1/assessment" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Success Response (200):**
```json
{
  "enrollment_id": 1,
  "student": "Juan Dela Cruz",
  "tuition": {
    "discount": 0,
    "total_lectures": 15000.00,
    "total_laboratory": 2500.00,
    "total_tuition": 17500.00,
    "total_miscelaneous_fees": 3500.00,
    "overall_tuition": 21000.00,
    "downpayment": 5000.00,
    "total_balance": 16000.00
  },
  "additional_fees": [
    {
      "fee_name": "Late Enrollment Fee",
      "amount": 500.00,
      "description": "Applied for late enrollment"
    }
  ],
  "additional_fees_total": 500.00,
  "subjects_enrolled": [
    {
      "code": "IT 111",
      "title": "Introduction to Computing",
      "lecture_units": 3,
      "laboratory_units": 1,
      "lecture_fee": 1000.00,
      "laboratory_fee": 500.00
    }
  ],
  "assessment_url": "https://api.koakademy.edu/storage/enrollments/1/assessment-1.pdf"
}
```

---

## Error Responses

### Validation Error (422)
```json
{
  "message": "Validation failed",
  "errors": {
    "student_id": [
      "The student id field is required."
    ],
    "course_id": [
      "The selected course id is invalid."
    ]
  }
}
```

### Not Found (404)
```json
{
  "message": "No query results for model [App\\Models\\StudentEnrollment] 999"
}
```

### Unauthorized (401)
```json
{
  "message": "Unauthenticated."
}
```

### Server Error (500)
```json
{
  "message": "Failed to create enrollment",
  "error": "Detailed error message here"
}
```

---

## Data Relationships

The API uses eager loading to efficiently load related data. The following relationships are automatically loaded:

- `student` - Student information
- `student.course` - Student's course
- `course` - Enrollment course
- `subjectsEnrolled` - Enrolled subjects
- `subjectsEnrolled.subject` - Subject details
- `subjectsEnrolled.class` - Class information
- `subjectsEnrolled.class.faculty` - Faculty/instructor
- `subjectsEnrolled.class.schedule` - Class schedules
- `subjectsEnrolled.class.schedule.room` - Room assignments
- `studentTuition` - Tuition assessment
- `additionalFees` - Additional fees
- `resources` - Related files (assessments, certificates)

---

## Best Practices

1. **Use Pagination**: Always use the `per_page` parameter to limit results and improve performance.

2. **Filter by Current Period**: Use `current_period=true` to get only relevant enrollments for the active school year/semester.

3. **Selective Loading**: Only request transactions when needed using `with_transactions=true`.

4. **Search Efficiently**: Use the `search` parameter instead of loading all records and filtering client-side.

5. **Handle Soft Deletes**: Be aware that deleted enrollments are excluded by default. Use `with_trashed=true` to include them.

6. **Validate Input**: Always validate data before sending to prevent validation errors.

7. **Error Handling**: Implement proper error handling for all API responses (422, 404, 500, etc.).

---

## Rate Limiting

The API uses Laravel Sanctum's default rate limiting. Ensure your application handles rate limit errors appropriately.

---

## Version

API Version: 1.0
Last Updated: 2024-11-30
