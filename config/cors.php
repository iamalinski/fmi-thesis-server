<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'], // Routes where CORS is applied
    'allowed_methods' => ['*'], // Allowed HTTP methods
    'allowed_origins' => ['http://localhost:5173'], // Replace with your frontend URLs
    'allowed_origins_patterns' => [], // Regex patterns for allowed origins
    'allowed_headers' => ['*'], // Allowed headers
    'exposed_headers' => [], // Headers exposed to the client
    'max_age' => 0, // Max age for preflight requests
    'supports_credentials' => true, // Changed to true to allow credentials
];