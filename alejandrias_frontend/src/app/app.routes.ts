import { Routes } from '@angular/router';
import { Login } from './Login/login/login';
import { Register } from './Login/register/register';
import { Home } from './Home/home/home';
import { authGuard } from './guards/auth-guard';
 
export const routes: Routes = [
    {path: 'login', component: Login },
    {path: 'register', component: Register },
    {path: 'home', component: Home,canActivate: [authGuard] },
];
