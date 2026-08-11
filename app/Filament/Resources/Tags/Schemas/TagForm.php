<?php

namespace App\Filament\Resources\Tags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات برچسب')
                ->schema([
                    TextInput::make('name')
                        ->label('نام برچسب')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn($state, callable $set) => $set('slug', static::makeSlug($state))),


                    TextInput::make('slug')
                        ->label('نامک')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn($state, callable $set) => $set('slug', static::makeSlug($state)))->rule(fn(?Model $record) => static::uniqueSlugRule('tags', $record)),

                ])
                ->columns(2),
        ]);
    }

    protected static function makeSlug(?string $value): string
    {
        // اگه slug فارسی خالص بود، transliterate کن
        $slug = Str::slug((string) $value);

        if (blank($slug)) {
            $slug = Str::slug(Str::ascii((string) $value));
        }

        if (blank($slug)) {
            // فارسی را حرف‌به‌حرف به لاتین نگاشت نمی‌کنه — یه fallback خوانا بساز
            $slug = 'tag-' . Str::lower(Str::random(6));
        }

        return $slug;
    }


    protected static function uniqueSlugRule(string $table, ?Model $record = null): Unique
    {
        $rule = Rule::unique($table, 'slug');

        if ($record?->exists) {
            $rule->ignore($record->getKey());
        }

        return $rule;
    }
}
