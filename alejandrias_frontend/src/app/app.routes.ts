import { Routes } from '@angular/router';
import { Login } from './Login/login/login';
import { Register } from './Login/register/register';
import { CrearForo } from './Foro/crear-foro/crear-foro';
import { ListaForos } from './Foro/lista-foros/lista-foros';
 
export const routes: Routes = [
    {path: 'login', component: Login },
    {path: 'register', component: Register },
    {path: 'crear_foro', component: CrearForo},
    { path: '', redirectTo: 'foros', pathMatch: 'full'},
    { path: 'foros', component: ListaForos }
];
