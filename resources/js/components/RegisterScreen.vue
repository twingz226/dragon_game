<template>
  <div class="auth-container">
    <div class="auth-card">
      <div class="logo">
        <h1 class="glitch" data-text="DINO RACE">DINO RACE</h1>
        <p class="subtitle">Create your account</p>
      </div>
      
      <form @submit.prevent="handleRegister" class="auth-form">
        <div v-if="error" class="error-message">
          {{ error }}
        </div>
        
        <div v-if="success" class="success-message">
          {{ success }}
        </div>

        <div class="form-group">
          <label for="name">Name</label>
          <input
            type="text"
            id="name"
            v-model="form.name"
            required
            :disabled="loading"
            placeholder="Enter your name"
          />
        </div>

        <div class="form-group">
          <label for="email">Email Address</label>
          <input
            type="email"
            id="email"
            v-model="form.email"
            required
            :disabled="loading"
            placeholder="Enter your email"
          />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input
            type="password"
            id="password"
            v-model="form.password"
            required
            :disabled="loading"
            placeholder="Enter your password"
            minlength="6"
          />
        </div>

        <div class="form-group">
          <label for="password_confirmation">Confirm Password</label>
          <input
            type="password"
            id="password_confirmation"
            v-model="form.password_confirmation"
            required
            :disabled="loading"
            placeholder="Confirm your password"
            minlength="6"
          />
        </div>

        <button type="submit" class="btn-primary" :disabled="loading">
          <span v-if="loading">Creating account...</span>
          <span v-else>Register</span>
        </button>
      </form>

      <div class="auth-links">
        <p>Already have an account? 
          <a href="#" @click.prevent="$emit('switch-to-login')" class="link">
            Login here
          </a>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';

const emit = defineEmits(['switch-to-login', 'register-success']);

const loading = ref(false);
const error = ref('');
const success = ref('');

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
});

const handleRegister = async () => {
  loading.value = true;
  error.value = '';
  success.value = '';

  // Client-side validation
  if (form.password !== form.password_confirmation) {
    error.value = 'Passwords do not match.';
    loading.value = false;
    return;
  }

  if (form.password.length < 6) {
    error.value = 'Password must be at least 6 characters.';
    loading.value = false;
    return;
  }

  try {
    const response = await fetch('/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                        document.querySelector('input[name="_token"]')?.value
      },
      body: JSON.stringify(form)
    });

    const data = await response.json();

    if (data.success) {
      success.value = data.message;
      emit('register-success', data.user);
      
      // Redirect to game after successful registration
      setTimeout(() => {
        window.location.href = '/game';
      }, 1000);
    } else {
      // Handle validation errors
      if (data.errors) {
        const errorMessages = Object.values(data.errors).flat();
        error.value = errorMessages[0] || 'Registration failed. Please try again.';
      } else {
        error.value = 'Registration failed. Please try again.';
      }
    }
  } catch (err) {
    error.value = 'Network error. Please try again.';
    console.error('Registration error:', err);
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.auth-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
  padding: 2rem;
}

.auth-card {
  background: rgba(30, 41, 59, 0.9);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(59, 130, 246, 0.3);
  border-radius: 16px;
  padding: 3rem;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
}

.logo {
  text-align: center;
  margin-bottom: 2rem;
}

.logo h1 {
  font-family: 'Press Start 2P', cursive;
  font-size: 1.5rem;
  color: #38bdf8;
  margin: 0 0 1rem 0;
  text-shadow: 2px 2px #0ea5e9;
}

.subtitle {
  color: #94a3b8;
  margin: 0;
  font-size: 0.9rem;
}

.auth-form {
  margin-bottom: 1.5rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  color: #94a3b8;
  font-weight: 500;
  font-size: 0.9rem;
}

.form-group input {
  width: 100%;
  padding: 0.75rem;
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid rgba(148, 163, 184, 0.2);
  border-radius: 8px;
  color: #f8fafc;
  font-size: 1rem;
  transition: all 0.3s ease;
}

.form-group input:focus {
  outline: none;
  border-color: #38bdf8;
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.1);
}

.form-group input:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  width: 100%;
  padding: 0.75rem;
  background: linear-gradient(135deg, #38bdf8 0%, #0ea5e9 100%);
  border: none;
  border-radius: 8px;
  color: white;
  font-family: 'Orbitron', sans-serif;
  font-weight: 700;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.3s ease;
  text-transform: uppercase;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px -5px rgba(56, 189, 248, 0.5);
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.error-message {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #f87171;
  padding: 0.75rem;
  border-radius: 8px;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}

.success-message {
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.3);
  color: #4ade80;
  padding: 0.75rem;
  border-radius: 8px;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}

.auth-links {
  text-align: center;
}

.auth-links p {
  color: #94a3b8;
  margin: 0;
  font-size: 0.9rem;
}

.link {
  color: #38bdf8;
  text-decoration: none;
  transition: color 0.3s ease;
}

.link:hover {
  color: #0ea5e9;
  text-decoration: underline;
}

/* Glitch effect */
.glitch {
  position: relative;
}

.glitch::before,
.glitch::after {
  content: attr(data-text);
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0.8;
}

.glitch::before {
  color: #ff00ff;
  z-index: -1;
  animation: glitch 3s cubic-bezier(0.25, 0.46, 0.45, 0.94) both infinite;
}

.glitch::after {
  color: #00ffff;
  z-index: -2;
  animation: glitch 3s cubic-bezier(0.25, 0.46, 0.45, 0.94) reverse both infinite;
}

@keyframes glitch {
  0% { transform: translate(0); }
  20% { transform: translate(-2px, 2px); }
  40% { transform: translate(-2px, -2px); }
  60% { transform: translate(2px, 2px); }
  80% { transform: translate(2px, -2px); }
  100% { transform: translate(0); }
}

@media (max-width: 768px) {
  .auth-container {
    padding: 1rem;
  }
  
  .auth-card {
    padding: 2rem;
  }
  
  .logo h1 {
    font-size: 1.2rem;
  }
}
</style>
