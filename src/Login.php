<?php

declare(strict_types=1);

namespace Scheduler;

use Atk4\Ui\Form;

final class Login
{
    public static function render(string $role): void
    {
        $app = new App();
        $form = Form::addTo($app);
        $form->addControl('password', [Form\Control\Password::class], ['required' => true]);
        $form->buttonSave->set('Enter');
        $form->onSubmit(static function (Form $form) use ($app, $role) {
            if (!Security::throttle('login-' . $role, 20, 300)) {
                return $form->jsError('password', 'Pārāk daudz mēģinājumu. Lūdzu, uzgaidiet 5 minūtes.');
            }
            if (Security::login($role, (string) $form->entity->get('password'))) {
                return $app->jsRedirect([$role === 'admin' ? 'admin' : 'teachers']);
            }

            return $form->jsError('password', 'Nepareiza parole.');
        });
    }
}
