@include('errors.layout', [
    'title' => 'Forbidden',
    'code' => '403',
    'heading' => 'This page isn’t for you.',
    'message' => 'You don’t have permission to open this. If you were looking for a serial or a doctor, head back and try from the public pages.',
])
