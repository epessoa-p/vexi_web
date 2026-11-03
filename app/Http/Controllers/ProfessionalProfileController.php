<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Datos profesionales del usuario que inicia sesión (§12.3): matrícula,
 * especialidad y firma. Aparecen en las recetas que emite.
 */
class ProfessionalProfileController extends Controller
{
    public function edit()
    {
        return view('profile.professional', ['user' => auth()->user()]);
    }

    public function update()
    {
        $user = auth()->user();

        $validated = request()->validate([
            'professional_license' => 'nullable|string|max:60',
            'specialty' => 'nullable|string|max:120',
            'signature' => 'nullable|image|max:2048',
        ], [], [
            'professional_license' => 'matrícula',
            'specialty' => 'especialidad',
            'signature' => 'firma',
        ]);

        $data = collect($validated)->except('signature')->all();

        if (request()->boolean('remove_signature') || request()->hasFile('signature')) {
            if ($user->signature) {
                Storage::disk('public')->delete($user->signature);
            }
            $data['signature'] = request()->file('signature')?->store('signatures', 'public');
        }

        $user->update($data);

        return redirect()->route('profile.professional')->with('success', 'Perfil profesional actualizado.');
    }
}
