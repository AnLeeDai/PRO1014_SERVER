import {
  getUsers,
  createUser,
  updateUser,
  deleteUser,
} from "./user.service.js";

document.addEventListener("DOMContentLoaded", async () => await renderUsers());

const renderUsers = async () => {
  const userList = document.getElementById("userList");
  userList.innerHTML = "";

  const users = await getUsers();

  if (users.length === 0) {
    userList.innerHTML =
      "<tr><td colspan='3' class='text-center py-4'>Không có người dùng nào.</td></tr>";
    return;
  }

  users.forEach((user) => {
    const row = document.createElement("tr");
    row.className = "border-t";

    row.innerHTML = `
      <td class="px-4 py-2">${user.name}</td>
      <td class="px-4 py-2">${user.email}</td>
      <td class="px-4 py-2 text-center">
          <button class="bg-green-500 text-white px-3 py-1 rounded-lg hover:bg-green-600 edit-btn"
                  data-id="${user.id}" data-name="${user.name}" data-email="${user.email}">Sửa</button>
          <button class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 ml-2 delete-btn"
                  data-id="${user.id}">Xóa</button>
      </td>
    `;

    userList.appendChild(row);
  });

  document.querySelectorAll(".edit-btn").forEach((btn) => {
    btn.addEventListener("click", () =>
      editUser(btn.dataset.id, btn.dataset.name, btn.dataset.email)
    );
  });

  document.querySelectorAll(".delete-btn").forEach((btn) => {
    btn.addEventListener("click", () => removeUser(btn.dataset.id));
  });
};

const addUser = async (e) => {
  e.preventDefault();
  const name = document.getElementById("name").value;
  const email = document.getElementById("email").value;

  await createUser({ name, email });
  await renderUsers();
  e.target.reset();
};

const editUser = (id, name, email) => {
  document.getElementById("editId").value = id;
  document.getElementById("editName").value = name;
  document.getElementById("editEmail").value = email;
  document.getElementById("editUserModal").classList.remove("hidden");
};

const closeEditModal = () => {
  document.getElementById("editUserModal").classList.add("hidden");
};

const updateUserHandler = async (e) => {
  e.preventDefault();
  const id = document.getElementById("editId").value;
  const name = document.getElementById("editName").value;
  const email = document.getElementById("editEmail").value;

  await updateUser({ id, name, email });
  await renderUsers();
  closeEditModal();
};

const removeUser = async (id) => {
  if (confirm("Bạn có chắc muốn xóa người dùng này?")) {
    await deleteUser(id);
    await renderUsers();
  }
};

document.getElementById("addUserForm").addEventListener("submit", addUser);
document
  .getElementById("editUserForm")
  .addEventListener("submit", updateUserHandler);
document
  .getElementById("closeEditModal")
  .addEventListener("click", closeEditModal);
