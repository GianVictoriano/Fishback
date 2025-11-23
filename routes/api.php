<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GroupChatController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\PlagController;
use App\Http\Controllers\ReviewContentController;
use App\Http\Controllers\ReviewCommentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ScrumBoardController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewImageController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\ContributionController;
use App\Http\Controllers\Api\ApplicantController;
use App\Http\Controllers\ArticleBookmarkController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Api\ApplicationPeriodController;
use App\Http\Controllers\Api\FolioController;
use App\Http\Controllers\ImportantNoteController;
use App\Http\Controllers\Api\CoverageRequestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\DocumentApprovalController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check & branding
Route::get('/ping', function () {
    return ['message' => 'API is working!'];
});
Route::get('/branding', [BrandingController::class, 'index']);

// Debug Facebook posting
Route::get('/debug-facebook', [ArticleController::class, 'debugFacebook']);

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);
Route::post('/plagiarism-webhook', [PlagController::class, 'webhook']);
Route::post('/auth/google', [AuthController::class, 'handleGoogleCallback']);
Route::post('/google/access-token', [\App\Http\Controllers\GoogleController::class, 'getAccessToken']);
Route::post('/login-as', [AuthController::class, 'loginAs']);

// Public application period check
Route::get('/application-period', [ApplicationPeriodController::class, 'index']);
Route::get('/application-period/status', [ApplicationPeriodController::class, 'checkStatus']);

// Public topic routes
Route::get('/topics', [TopicController::class, 'index']);
Route::get('/topics/{topic}', [TopicController::class, 'show']);

// Contributions
Route::middleware('force.api.auth')->group(function () {
    Route::apiResource('contributions', ContributionController::class)->only(['index', 'store', 'show']);
    Route::post('contributions/{contribution}/status', [ContributionController::class, 'updateStatus']);
});

// Protected topic and comment routes
Route::middleware('force.api.auth')->group(function () {
    // Topic routes
    Route::post('/topics', [TopicController::class, 'store']);
    Route::post('/topics/{topic}/report', [TopicController::class, 'report']);
    Route::delete('/topics/{topic}', [TopicController::class, 'delete']);
    
    // Comment routes
    Route::post('/topics/{topic}/comments', [CommentController::class, 'store']);
    Route::post('/comments/{comment}/report', [CommentController::class, 'report']);
    Route::delete('/comments/{comment}', [CommentController::class, 'delete']);
});
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::get('/users/{userId}/bookmarks', [ArticleBookmarkController::class, 'getUserBookmarks']);
Route::get('/public/articles', [ArticleController::class, 'publicArticles']);
Route::get('/public/article-summaries', [ArticleController::class, 'publicArticleSummaries']);
Route::get('/public/trending-articles', [ArticleController::class, 'trendingArticles']);
Route::get('/public/featured-articles', [ArticleController::class, 'publicFeaturedArticles']);
Route::get('/public/articles/{article}', [ArticleController::class, 'show']);
Route::post('/public/articles/{article}/react', [ArticleController::class, 'react']);
Route::post('/public/articles/{article}/visit', [ArticleController::class, 'visit']);
Route::get('/public/recommendations', [ArticleController::class, 'recommendations']);
Route::get('/public/activity-data', [ArticleController::class, 'getActivityData']);

// Protected routes
Route::middleware('force.api.auth')->group(function () {
    // Auth/User
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn(Request $request) => $request->user());
    Route::get('/users/me', [UserController::class, 'me']);
    
    // User registration/application to become collaborator
    Route::post('/applications', [ApplicantController::class, 'store']);
    
    // Application management (admin only)
    Route::get('/applications', [\App\Http\Controllers\Api\ApplicantController::class, 'index']);
    Route::get('/applications/{id}', [\App\Http\Controllers\Api\ApplicantController::class, 'show']);
    Route::put('/applications/{id}', [\App\Http\Controllers\Api\ApplicantController::class, 'update']);
    Route::post('/applications/{id}/accept', [\App\Http\Controllers\Api\ApplicantController::class, 'accept']);
    Route::delete('/applications/{id}', [\App\Http\Controllers\Api\ApplicantController::class, 'destroy']);

    // Application period management (admin only)
    Route::post('/application-period', [ApplicationPeriodController::class, 'store']);
    Route::delete('/application-period/{id}', [ApplicationPeriodController::class, 'destroy']);

    // Email sending
    Route::post('/send-email', [\App\Http\Controllers\Api\EmailController::class, 'sendEmail']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    
    // Articles - Moved to the top to ensure they're protected
    Route::apiResource('articles', ArticleController::class)->except(['index', 'show']);
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{article}', [ArticleController::class, 'show']);
    Route::get('/recommendations', [ArticleController::class, 'recommendations']);
    Route::post('/articles/{article}/interaction', [ArticleController::class, 'recordInteraction']);
    Route::post('/upload-media', [ArticleController::class, 'uploadMedia']);
    Route::get('/dashboard-stats', [ArticleController::class, 'getDashboardStats']);
    Route::get('/contributors', [ArticleController::class, 'getAllContributors']);
    Route::get('/graph-data', [ArticleController::class, 'getGraphData']);
    
    // Media Management
    Route::get('/media/articles', [ArticleController::class, 'getMediaArticles']);
    Route::patch('/articles/{article}/archive', [ArticleController::class, 'toggleArchive']);
    Route::patch('/articles/{article}/feature', [ArticleController::class, 'toggleFeatured']);
    Route::get('/articles/featured', [ArticleController::class, 'getFeaturedArticles']);
    Route::delete('/articles/{article}', [ArticleController::class, 'deleteArticle']);
    
    Route::get('/bookmarks/article/{articleId}', [ArticleBookmarkController::class, 'getByArticle']);
    Route::post('/bookmarks', [ArticleBookmarkController::class, 'store']);
    Route::patch('/bookmarks/{id}', [ArticleBookmarkController::class, 'update']);
    Route::delete('/bookmarks/{id}', [ArticleBookmarkController::class, 'destroy']);

    Route::post('/upload-media', [ArticleController::class, 'uploadMedia']);

    // Branding
    Route::post('/branding', [BrandingController::class, 'update']);
    Route::post('/branding/reset', [BrandingController::class, 'reset']);

    // Group Chat & Messages
    Route::get('/group-chats', [GroupChatController::class, 'index']);
    Route::get('/group-chats/{groupChat}', [GroupChatController::class, 'show']);
    Route::get('/group-chats/{groupChat}/messages', [ChatMessageController::class, 'index']);
    Route::post('/group-chats/{groupChat}/messages', [ChatMessageController::class, 'store']);
    Route::get('/group-chats/{groupChat}/members', [GroupChatController::class, 'getMembers']);
    Route::patch('/group-chats/{groupChat}/status', [GroupChatController::class, 'updateStatus']);

    // Plagiarism Scans
    Route::post('/plagiarism-scans', [PlagController::class, 'submitScan']);
    Route::get('/plagiarism-scans/{scanId}', [PlagController::class, 'checkStatus']);

    Route::post('/review-images', [ReviewImageController::class, 'store']);
    Route::patch('/review-images/{id}/approve', [ReviewImageController::class, 'approve']);
    Route::patch('/review-images/{id}/reject', [ReviewImageController::class, 'reject']);
    Route::patch('/review-images/{id}', [ReviewImageController::class, 'update']);
    Route::get('/review-images/{id}', [ReviewImageController::class, 'show']);
    Route::get('/review-images', [ReviewImageController::class, 'index']);
    
    // Review Content
    Route::get('/review-content', [ReviewContentController::class, 'index']);
    Route::get('/review-content/versions', [ReviewContentController::class, 'versions']);
    Route::get('/review-content/preview/{id}', [ReviewContentController::class, 'preview']);
    Route::get('/review-content/{id}', [ReviewContentController::class, 'show']);
    Route::post('/review-content', [ReviewContentController::class, 'store']);
    Route::patch('/review-content/{id}', [ReviewContentController::class, 'update']);
    Route::patch('/review-content/{id}/approve', [ReviewContentController::class, 'approve']);
    Route::patch('/review-content/{id}/reject', [ReviewContentController::class, 'reject']);

    // Review Comments
    Route::get('/review-comments/{reviewContentId}', [ReviewCommentController::class, 'index']);
    Route::post('/review-comments', [ReviewCommentController::class, 'store']);
    Route::patch('/review-comments/{id}', [ReviewCommentController::class, 'update']);
    Route::delete('/review-comments/{id}', [ReviewCommentController::class, 'destroy']);

    // Module & Collaborators
    Route::get('/modules', [ModuleController::class, 'getModules']);
    Route::get('/collaborators', [ModuleController::class, 'getCollaborators']);
    Route::patch('/collaborators/{profile}', [ModuleController::class, 'updatePosition']);
    Route::post('/collaborators/{profile}/modules', [ModuleController::class, 'updateCollaboratorModules']);

    // Google Docs
    Route::post('/google/create-doc', [\App\Http\Controllers\GoogleController::class, 'createDoc']);

    // Scrum Board
    Route::post('/scrum-boards', [ScrumBoardController::class, 'store']);

    // Activities
    Route::get('/activities', [ActivityController::class, 'index']);
    Route::post('/activities', [ActivityController::class, 'store']);
    Route::get('/activities/{activity}', [ActivityController::class, 'show']);
    Route::put('/activities/{activity}', [ActivityController::class, 'update']);
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy']);
    Route::post('/activities/{activity}/member-status', [ActivityController::class, 'updateMemberStatus']);
    Route::post('/activities/{activity}/members', [ActivityController::class, 'addMember']);
    Route::delete('/activities/{activity}/members/{user}', [ActivityController::class, 'removeMember']);

    // Forum Topics & Comments
    Route::post('/topics', [TopicController::class, 'store']);
    Route::post('/topics/{topic}/comments', [CommentController::class, 'store']);

    // Posts
    Route::post('/posts', [PostController::class, 'store']);

    // Folios (Literary Folios)
    Route::get('/folios', [FolioController::class, 'index']);
    Route::post('/folios', [FolioController::class, 'store']);
    Route::get('/folios/{id}', [FolioController::class, 'show']);
    Route::put('/folios/{id}', [FolioController::class, 'update']);
    Route::delete('/folios/{id}', [FolioController::class, 'destroy']);
    
    // Folio submissions
    Route::post('/folios/{id}/submit', [FolioController::class, 'submitWork']);
    Route::get('/folios/{id}/submissions', [FolioController::class, 'getSubmissions']);
    Route::post('/folios/{folioId}/submissions/{submissionId}/review', [FolioController::class, 'reviewSubmission']);
    
    // Folio members
    Route::post('/folios/{id}/members', [FolioController::class, 'addMember']);
    Route::delete('/folios/{id}/members/{userId}', [FolioController::class, 'removeMember']);

    // Coverage Requests
    Route::get('/coverage-requests', [CoverageRequestController::class, 'index']);
    Route::post('/coverage-requests', [CoverageRequestController::class, 'store']);
    Route::get('/coverage-requests/{id}', [CoverageRequestController::class, 'show']);
    Route::post('/coverage-requests/{id}/approve', [CoverageRequestController::class, 'approve']);
    Route::post('/coverage-requests/{id}/reject', [CoverageRequestController::class, 'reject']);
    Route::delete('/coverage-requests/{id}', [CoverageRequestController::class, 'destroy']);

    // Important Notes
    Route::get('/important-notes', [ImportantNoteController::class, 'index']);
    Route::post('/important-notes', [ImportantNoteController::class, 'store']);
    Route::get('/important-notes/{id}', [ImportantNoteController::class, 'show']);
    Route::patch('/important-notes/{id}', [ImportantNoteController::class, 'update']);
    Route::delete('/important-notes/{id}', [ImportantNoteController::class, 'destroy']);

    // Dashboard Statistics
    Route::get('/dashboard/statistics', [DashboardController::class, 'getStatistics']);

    // Document Approval Tracking
    Route::get('/document-approval/workflow', [DocumentApprovalController::class, 'getWorkflow']);

    // Literary Works (Heyzine Integration)
    Route::post('/literary-works', [App\Http\Controllers\Api\LiteraryWorkController::class, 'store']);
    Route::get('/literary-works', [App\Http\Controllers\Api\LiteraryWorkController::class, 'index']);
    Route::get('/literary-works/{id}', [App\Http\Controllers\Api\LiteraryWorkController::class, 'show']);

    // Submissions (Artwork, Literature, Photography)
    Route::apiResource('submissions', App\Http\Controllers\SubmissionController::class);
    Route::get('/my-submissions', [App\Http\Controllers\SubmissionController::class, 'mySubmissions']);
});