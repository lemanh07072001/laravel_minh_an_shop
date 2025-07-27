<?php

namespace App\Http\Requests;

use App\Enums\StatusUserEnum;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $this->route('id'),
            'password' => 'nullable|string|min:6',
            'phone'    => 'nullable|string',
            'address'  => 'nullable|string',
            'note'     => 'nullable|string',
            'status'   => ['required', 'string', Rule::in(User::STAUS_KEY)],

        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Tên không được để trống.',
            'email.required'    => 'Email không được để trống.',
            'email.email'       => 'Email không đúng định dạng.',
            'email.unique'      => 'Email đã tồn tại.',
            'password.min'      => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'status.required'   => 'Trạng thái là bắt buộc.',

        ];
    }
}
