// Shapes of the JSON the Laravel API returns. Keep in sync with app/Http/Resources.

export type Role = 'admin' | 'manager' | 'staff'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  role_label: string
  is_demo: boolean
}

/** Laravel API Resources wrap single objects in { data: ... } */
export interface Resource<T> {
  data: T
}
