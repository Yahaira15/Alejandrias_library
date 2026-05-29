import { Routes } from '@angular/router';
import { Login } from './Login/login/login';
import { Register } from './Login/register/register';
import { Intereses } from './Login/intereses/intereses';
import { CrearForo } from './Foro/crear-foro/crear-foro';
import { ListaForos } from './Foro/lista-foros/lista-foros';
import { MisForos } from './Foro/mis-foros/mis-foros';
import { EditarForo } from './Foro/editar-foro/editar-foro';
import { authGuard } from './guards/auth-guard';
import { publicacionRegistroGuard } from './guards/publicacion-registro-guard';
import { Home } from './Home/home/home';
import { Perfil } from './perfil/perfil';
import { VerPublicacionComponent } from './Foro/ver-publicacion/ver-publicacion';
import { VerForoComponent } from './Foro/ver-foro/ver-foro';
import { ChatIaComponent } from './chat-ia/chat-ia';
import { adminGuard } from './guards/admin-guard';
import { moderationReviewerGuard } from './guards/moderation-reviewer-guard';
import { AdminLayout } from './Admin/admin-layout/admin-layout';
import { AdminUsuarios } from './Admin/admin-usuarios/admin-usuarios';
import { AdminForos } from './Admin/admin-foros/admin-foros';
import { AdminCategorias } from './Admin/admin-categorias/admin-categorias';
import { AdminPublicaciones } from './Admin/admin-publicaciones/admin-publicaciones';
import { AdminComentarios } from './Admin/admin-comentarios/admin-comentarios';
import { AdminReportes } from './Admin/admin-reportes/admin-reportes';
import { AdminSanciones } from './Admin/admin-sanciones/admin-sanciones';
import { AdminModeracion } from './Admin/admin-moderacion/admin-moderacion';
 
export const routes: Routes = [
    {path: 'home',component:Home},
    {path: 'login', component: Login },
    {path: 'register', component: Register },
    {path: 'intereses', component: Intereses, canActivate: [authGuard] },
    {path: 'chat-ia', component: ChatIaComponent, canActivate: [authGuard] },
    {path: 'foros/crear', component: CrearForo, canActivate: [authGuard] },
    { path: 'foros/:foro_id', component: VerForoComponent, canActivate: [authGuard] },
    { path: 'publicaciones/:publicacion_id', component: VerPublicacionComponent, canActivate: [authGuard, publicacionRegistroGuard] },
    { path: '', redirectTo: 'home', pathMatch: 'full'},
    { path: 'foros', component: ListaForos, canActivate: [authGuard] },
    { path: 'mis-foros', component: MisForos, canActivate: [authGuard] },
    { path: 'foros/editar/:id', component: EditarForo, canActivate: [authGuard] },
    { path: 'perfil', component: Perfil, canActivate: [authGuard] },
    {
        path: 'admin',
        component: AdminLayout,
        canActivate: [authGuard, moderationReviewerGuard],
        children: [
            { path: '', redirectTo: 'moderacion', pathMatch: 'full' },
            { path: 'moderacion', component: AdminModeracion },
            { path: 'usuarios', component: AdminUsuarios, canActivate: [adminGuard] },
            { path: 'foros', component: AdminForos, canActivate: [adminGuard] },
            { path: 'categorias', component: AdminCategorias, canActivate: [adminGuard] },
            { path: 'publicaciones', component: AdminPublicaciones, canActivate: [adminGuard] },
            { path: 'comentarios', component: AdminComentarios, canActivate: [adminGuard] },
            { path: 'reportes', component: AdminReportes, canActivate: [adminGuard] },
            { path: 'sanciones', component: AdminSanciones, canActivate: [adminGuard] }
        ]
    }
];
