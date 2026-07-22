import { NextFunction, Request, Response } from 'express'

type AsyncRouteHandler<Req extends Request = Request> = (req: Req, res: Response, next: NextFunction) => Promise<void>

// Express 4 não encaminha rejeições de promise para o error handler automaticamente —
// sem isso, um erro do banco (ex: UUID inválido) derruba o processo Node inteiro.
export function asyncHandler<Req extends Request = Request>(handler: AsyncRouteHandler<Req>) {
  return (req: Req, res: Response, next: NextFunction): void => {
    handler(req, res, next).catch(next)
  }
}
