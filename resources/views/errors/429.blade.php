@include('errors.layout', [
    'title' => 'Too Many Requests',
    'code' => '429',
    'heading' => 'Too many tries.',
    'message' => 'Wait a minute, then try again. This keeps the chamber pages from being overloaded.',
])
