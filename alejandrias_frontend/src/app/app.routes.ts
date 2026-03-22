import { Routes } from '@angular/router';
import { Login } from './Login/login/login';
import { Register } from './Login/register/register';
 
export const routes: Routes = [
    {path: 'login', component: Login },
    {path: 'register', component: Register }
];
