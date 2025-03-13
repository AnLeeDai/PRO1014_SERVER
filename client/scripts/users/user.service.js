const BASE_URL = "http://localhost/PRO1014/server/public/";

// Lấy danh sách người dùng
export async function getUsers() {
  try {
    const response = await fetch(`${BASE_URL}?request=get-users`);
    const data = await response.json();
    return data.success ? data.data : [];
  } catch (error) {
    console.error("Lỗi khi lấy danh sách users:", error);
    return [];
  }
}

// Thêm người dùng mới
export async function createUser(name, email) {
  const response = await fetch(`${BASE_URL}?request=post-users`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ name, email }),
  });
  return await response.json();
}

// Cập nhật thông tin người dùng
export async function updateUser(id, name, email) {
  const response = await fetch(`${BASE_URL}?request=put-users&id=${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ name, email }),
  });
  return await response.json();
}

// Xóa người dùng
export async function deleteUser(id) {
  const response = await fetch(`${BASE_URL}?request=delete-users&id=${id}`, {
    method: "DELETE",
  });
  return await response.json();
}
