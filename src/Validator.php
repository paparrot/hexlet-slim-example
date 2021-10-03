<?php

namespace App;

class Validator
{
  public function validate(array $user): array
  {
    $errors = [];
    foreach ($user as $key => $value) {
      if ($user[$key] === '') {
        $errors[$key] = 'Обязательное поле';
      }
    }
    return $errors;
  }
}