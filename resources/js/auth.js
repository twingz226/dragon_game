import { createApp } from 'vue';
import LoginScreen from './components/LoginScreen.vue';
import RegisterScreen from './components/RegisterScreen.vue';

const app = createApp({
    components: {
        LoginScreen,
        RegisterScreen
    },
    
    data() {
        return {
            currentScreen: 'login', // login or register
            user: null
        };
    },
    
    methods: {
        switchToRegister() {
            this.currentScreen = 'register';
        },
        
        switchToLogin() {
            this.currentScreen = 'login';
        },
        
        handleLoginSuccess(user) {
            this.user = user;
            console.log('Login successful:', user);
        },
        
        handleRegisterSuccess(user) {
            this.user = user;
            console.log('Registration successful:', user);
        }
    },
    
    template: `
        <div>
            <LoginScreen 
                v-if="currentScreen === 'login'"
                @switch-to-register="switchToRegister"
                @login-success="handleLoginSuccess"
            />
            <RegisterScreen 
                v-if="currentScreen === 'register'"
                @switch-to-login="switchToLogin"
                @register-success="handleRegisterSuccess"
            />
        </div>
    `
});

app.mount('#app');
