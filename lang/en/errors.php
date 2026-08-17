<?php

declare(strict_types=1);

return [

    'go_back' => 'Go back',
    'go_home' => 'Go to start',

    '403_title' => 'You do not have access to this',
    '403_message' => 'Your account exists and is active, but this project or screen is outside what you can see. This is not a system error.',
    '403_action' => 'If you think you should have access, ask whoever administers the project to add you as a member. Managing someone on the team grants read access, not editing.',

    '404_title' => 'This is not here anymore',
    '404_message' => 'The address is valid but I found nothing at it. It may have been deleted, or the link may point to another project.',
    '404_action' => 'Check that the link is complete. If you came from an old email, that has probably changed by now.',

    '419_title' => 'The page was open for too long',
    '419_message' => 'For security, forms expire after a while unused. What you typed was not saved.',
    '419_action' => 'Go back, open the screen again and capture it once more. If this happens often, say so: it can be adjusted.',

    '429_title' => 'Too many attempts in a row',
    '429_message' => 'The system throttles repeated attempts to protect accounts. Nothing is broken.',
    '429_action' => 'Wait a minute and try again. If you are recovering your password, check your email first.',

    '500_title' => 'Something failed on our side',
    '500_message' => 'This was not something you did wrong. The error was logged with the exact time.',
    '500_action' => 'Try again. If it keeps happening, note what you were doing and at what time — that is enough to trace it in the log.',

    '503_title' => 'The system is under maintenance',
    '503_message' => 'An update is being applied. This is temporary and planned.',
    '503_action' => 'Try again in a few minutes. Nothing you already saved is lost.',

];
