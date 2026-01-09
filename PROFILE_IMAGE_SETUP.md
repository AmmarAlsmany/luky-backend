# Profile Image Upload Feature - Backend Setup

## Overview
Profile image upload functionality has been implemented for the mobile app (Flutter client).

## What Was Implemented

### 1. Controller Method
**File**: `app/Http/Controllers/Api/Auth/AuthController.php`
- Added `uploadProfileImage()` method (line 277)
- Accepts `profile_image` field in multipart/form-data
- Uses Spatie Media Library for optimized image storage
- Returns full user profile with updated image URL

### 2. API Route
**File**: `routes/api.php`
- Added: `POST /v1/user/profile/image`
- Requires authentication (sanctum middleware)
- Protected by 'active' and 'validate.app.type' middleware

### 3. UserResource Update
**File**: `app/Http/Resources/UserResource.php`
- Added `profile_image_url` field (line 27)
- Maps to User model's `avatar_url` accessor

### 4. User Model
**File**: `app/Models/User.php`
- Already has `getAvatarUrlAttribute()` accessor
- Uses Spatie Media Library 'avatar' collection
- Returns optimized image version
- Falls back to gender-based default avatars

## API Endpoint Details

### Request
```
POST /v1/user/profile/image
Content-Type: multipart/form-data
Authorization: Bearer {token}

Body:
- profile_image: File (image file)
```

### Validation Rules
- Required: image file
- Allowed types: jpeg, png, jpg
- Max size: 5MB (5120 KB)

### Response (Success)
```json
{
  "success": true,
  "message": "Profile image updated successfully",
  "data": {
    "id": 123,
    "name": "User Name",
    "phone": "+966...",
    "email": "user@example.com",
    "user_type": "client",
    "gender": "male",
    "profile_image_url": "https://your-domain.com/media/1/optimized-image.jpg",
    ...
  }
}
```

### Response (Error)
```json
{
  "success": false,
  "message": "Failed to upload profile image: {error details}"
}
```

## Image Processing
Images are automatically processed by Spatie Media Library:
1. Original image stored
2. Optimized version created (300x300 @ 85% quality)
3. Thumbnail created (100x100 @ 80% quality)
4. Old avatar automatically deleted before new upload

## Storage Location
- Images stored in: `storage/app/public/media/`
- Accessible via: `{APP_URL}/storage/media/{id}/{filename}`

## Default Avatars
If no profile image uploaded:
- Male users: Default male avatar
- Female users: Default female avatar

## Testing the Endpoint

### Using cURL
```bash
curl -X POST http://your-domain.com/api/v1/user/profile/image \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "profile_image=@/path/to/image.jpg"
```

### Using Postman
1. Method: POST
2. URL: `http://your-domain.com/api/v1/user/profile/image`
3. Headers: `Authorization: Bearer YOUR_TOKEN`
4. Body: form-data
   - Key: `profile_image`
   - Type: File
   - Value: Select image file

## Notes
- The existing `/auth/avatar` endpoint still works (uses 'avatar' field name)
- Profile images are optimized automatically for better performance
- Old avatars are deleted when new ones are uploaded
- Supports both legacy avatar field and new media library approach
- Gender-based fallback avatars if no image uploaded

## Requirements Met
✅ Accepts profile_image field
✅ Returns profile_image_url in response
✅ Handles authentication
✅ Validates file type and size
✅ Stores and optimizes images
✅ Returns updated user profile
✅ Compatible with Flutter app expectations
