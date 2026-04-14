import { Routes } from '@angular/router';
import { Login } from './Login/login/login';
import { Register } from './Login/register/register';
import { CrearForo } from './Foro/crear-foro/crear-foro';
import { ListaForos } from './Foro/lista-foros/lista-foros';
import { EditarForo } from './Foro/editar-foro/editar-foro';
import { authGuard } from './guards/auth-guard';
import { Home } from './Home/home/home';
import { Perfil } from './perfil/perfil';
import { VerPublicacionComponent } from './Foro/ver-publicacion/ver-publicacion';
import { VerForoComponent } from './Foro/ver-foro/ver-foro';
 
export const routes: Routes = [
    {path: 'home',component:Home},
    {path: 'login', component: Login },
    {path: 'register', component: Register },
    {path: 'foros/crear', component: CrearForo},
    { path: 'foros/:foro_id', component: VerForoComponent },
    { path: 'publicaciones/:publicacion_id', component: VerPublicacionComponent },
    { path: '', redirectTo: 'home', pathMatch: 'full'},
    { path: 'foros', component: ListaForos, canActivate: [authGuard] },
    { path: 'foros/editar/:id', component: EditarForo, canActivate: [authGuard] },
    { path: 'perfil', component: Perfil, canActivate: [authGuard] }
];
