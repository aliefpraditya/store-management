<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Keeps the people to call at a supplier or a carrier (a `contacts()` relation of name and phone) in line with the list a
 * form sends.
 */
class ContactSynchronizer
{
    /**
     * Makes the contacts match the given list: a line with the id of one of the owner's contacts updates it, any other
     * line adds one, and the contacts left out are deleted one by one so each deletion is audited. An id that belongs to
     * someone else's contact is not taken over: it adds a new one. The relation is cloned for every lookup because a query
     * on it keeps the conditions it was given (a `find` would otherwise limit the final delete to that one id).
     *
     * @param  HasMany<Model, Model>  $contacts  the owner's `contacts()` relation
     * @param  list<array{id?: int|null, name: string, phone?: string|null}>  $contactInputs
     */
    public function sync(HasMany $contacts, array $contactInputs): void
    {
        $keptContactIds = [];

        foreach ($contactInputs as $contactInput) {
            $contactDetails = [
                'name' => trim($contactInput['name']),
                'phone' => filled($contactInput['phone'] ?? null) ? trim($contactInput['phone']) : null,
            ];
            $contact = filled($contactInput['id'] ?? null) ? (clone $contacts)->find($contactInput['id']) : null;

            if ($contact === null) {
                $contact = $contacts->create($contactDetails);
            } else {
                $contact->update($contactDetails);
            }

            $keptContactIds[] = $contact->id;
        }

        (clone $contacts)->whereNotIn('id', $keptContactIds)->get()->each->delete();
    }
}
