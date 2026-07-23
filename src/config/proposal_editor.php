<?php

return [
    'shortcuts' => [
        [
            'keys' => 'Ctrl + S',
            'action' => 'Save without exiting',
            'description' => 'Save the current paper and keep the editor open.',
        ],
        [
            'keys' => 'Ctrl + Enter',
            'action' => 'Save and exit',
            'description' => 'Save the current paper, then exit the editor.',
        ],
        [
            'keys' => 'Ctrl + Alt + R',
            'action' => 'Discard changes',
            'description' => 'Discard unsaved changes and reload the current paper.',
        ],
        [
            'keys' => 'Ctrl + Alt + X',
            'action' => 'Exit editor',
            'description' => 'Discard unsaved changes and return to the proposal package.',
        ],
    ],
];
