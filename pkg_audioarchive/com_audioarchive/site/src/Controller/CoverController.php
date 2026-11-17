<?php
namespace Punga\Component\Audioarchive\Site\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Punga\Component\Audioarchive\Administrator\Service\CoverService;

\defined('_JEXEC') or die;

/** @brief CSRF-protected cover management with no public rendering endpoint. */
class CoverController extends BaseController
{
    /** @brief Validate a multipart action and return safe status metadata only. */
    public function manage(): void
    {
        $app = Factory::getApplication();
        $code = 200;
        try
        {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Session::checkToken('post'))
            {
                throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
            $input = $app->getInput();
            if ($input->post->getCmd('action') !== 'status')
            {
                throw new \RuntimeException(Text::_('COM_AUDIOARCHIVE_COVER_FORM_HELP'), 400);
            }
            $service = new CoverService(Factory::getContainer()->get(DatabaseInterface::class), ComponentHelper::getParams('com_audioarchive'), $app->getIdentity());
            $data = $service->manage($input->post->getCmd('kind'), $input->post->getString('id'), $input->post->getCmd('action'), $input->files->get('cover', [], 'array'));
            $result = ['success' => true, 'data' => $data];
        }
        catch (\Throwable $error)
        {
            $code = in_array($error->getCode(), [400, 403, 404], true) ? $error->getCode() : 500;
            $result = ['success' => false, 'message' => $code === 500 ? Text::_('COM_AUDIOARCHIVE_COVER_ERROR') : $error->getMessage()];
        }
        while (ob_get_level() > 0)
        {
            ob_end_clean();
        }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($result);
        $app->close();
    }
}
