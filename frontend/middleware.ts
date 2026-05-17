import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  const isPublicRoute = pathname.startsWith('/login') || 
                        pathname.startsWith('/register') || 
                        pathname.startsWith('/review');

  const isApiOrStatic = pathname.startsWith('/api') || 
                        pathname.startsWith('/_next') ||
                        pathname.match(/\.(.*)$/); // Match files with extensions

  if (isPublicRoute || isApiOrStatic) {
    return NextResponse.next();
  }

  const token = request.cookies.get('token')?.value || request.cookies.get('laravel_session')?.value;

  if (!token) {
    const loginUrl = new URL('/login', request.url);
    loginUrl.searchParams.set('redirect', pathname);
    return NextResponse.redirect(loginUrl);
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    /*
     * Match all request paths except for the ones starting with:
     * - api (API routes)
     * - _next/static (static files)
     * - _next/image (image optimization files)
     * - favicon.ico, sitemap.xml, robots.txt (metadata files)
     */
    '/((?!api|_next/static|_next/image|favicon.ico|sitemap.xml|robots.txt).*)',
  ],
};
