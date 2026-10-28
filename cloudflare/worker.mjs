const origin = new URL('https://vecaku-diena-cloudrun-irprdit76a-lz.a.run.app');

export default {
  async fetch(request) {
    const publicUrl = new URL(request.url);

    if (publicUrl.protocol !== 'https:' || publicUrl.hostname === 'www.esgribu.lv') {
      publicUrl.protocol = 'https:';
      publicUrl.hostname = 'esgribu.lv';
      publicUrl.port = '';

      return Response.redirect(publicUrl.toString(), 308);
    }

    const upstreamUrl = new URL(publicUrl);
    upstreamUrl.protocol = origin.protocol;
    upstreamUrl.host = origin.host;

    const upstreamRequest = new Request(upstreamUrl, request);
    upstreamRequest.headers.set('Host', origin.host);
    upstreamRequest.headers.set('X-Forwarded-Host', publicUrl.host);
    upstreamRequest.headers.set('X-Forwarded-Proto', 'https');

    const upstreamResponse = await fetch(upstreamRequest, {
      redirect: 'manual',
      cache: 'no-store',
    });
    const response = new Response(upstreamResponse.body, upstreamResponse);
    const location = response.headers.get('Location');

    if (location !== null) {
      const destination = new URL(location, upstreamUrl);

      if (destination.origin === origin.origin) {
        destination.protocol = publicUrl.protocol;
        destination.host = publicUrl.host;
        response.headers.set('Location', destination.toString());
      }
    }

    return response;
  },
};
