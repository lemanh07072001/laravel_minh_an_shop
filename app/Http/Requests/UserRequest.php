<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'phone'    => 'required|string',
            'address'  => 'nullable|string',
            'note'     => 'nullable|string',
            'status'   => 'required|integer',
            'role'     => 'required|integer',
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
            'phone.required'    => 'Số điện thoại không được để trống.',
            'status.required'   => 'Trạng thái là bắt buộc.',
            'role.required'     => 'Quyền là bắt buộc.',
        ];
    }
}
